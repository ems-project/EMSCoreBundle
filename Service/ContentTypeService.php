<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\NoResultException;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\SingleTypeIndex;
use EMS\CoreBundle\Exception\ContentTypeAlreadyExistException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\SingleTypeIndexRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface;

class ContentTypeService
{
    /** @var Registry $doctrine */
    protected $doctrine;

    /** @var LoggerInterface */
    protected $logger;
    
    /** @var Mapping*/
    private $mappingService;
    
    /** @var Client*/
    private $client;
    
    /** @var EnvironmentService $environmentService */
    private $environmentService;
    
    /** @var FormRegistryInterface $formRegistry*/
    private $formRegistry;
    
    /** @var TranslatorInterface $translator*/
    private $translator;
    
    private $instanceId;

    /** @var ContentType[]  */
    protected $orderedContentTypes = [];

    /** @var ContentType[]  */
    protected $contentTypeArrayByName = [];

    /** @var bool */
    protected $singleTypeIndex;



    public function __construct(Registry $doctrine, LoggerInterface $logger, Mapping $mappingService, Client $client, EnvironmentService $environmentService, FormRegistryInterface $formRegistry, TranslatorInterface $translator, $instanceId, $singleTypeIndex)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->mappingService = $mappingService;
        $this->client = $client;
        $this->environmentService = $environmentService;
        $this->formRegistry = $formRegistry;
        $this->instanceId = $instanceId;
        $this->translator = $translator;
        $this->singleTypeIndex = $singleTypeIndex;
    }


    /**
     * Get child by path
     *
     * @param FieldType $fieldType
     * @param string $path
     * @param bool $skipVirtualFields
     *
     * @return FieldType|false
     */
    public function getChildByPath(FieldType $fieldType, $path, $skipVirtualFields = false)
    {
        $elem = explode('.', $path);
        if (!empty($elem)) {
            /**@var FieldType $child*/
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $type = $child->getType();
                    if ($skipVirtualFields && $type::isVirtual($child->getOptions())) {
                        $fieldTypeByPath = $this->getChildByPath($child, $path, $skipVirtualFields);
                        if ($fieldTypeByPath) {
                            return $fieldTypeByPath;
                        }
                    } else if ($child->getName() == $elem[0]) {
                        if (strpos($path, ".")) {
                            $fieldTypeByPath = $this->getChildByPath($fieldType, substr($path, strpos($path, ".") + 1), $skipVirtualFields);
                            if ($fieldTypeByPath) {
                                return $fieldTypeByPath;
                            }
                        }
                        return $child;
                    }
                }
            }
        }
        return false;
    }

    private function loadEnvironment()
    {
        if ($this->orderedContentTypes === []) {
            /** @var ContentTypeRepository $contentTypeRepository */
            $contentTypeRepository = $this->doctrine->getManager()->getRepository('EMSCoreBundle:ContentType');
            $this->orderedContentTypes = $contentTypeRepository->findBy(['deleted' => false], ['orderKey' => 'ASC']);
            $this->contentTypeArrayByName = [];
            /** @var ContentType $contentType */
            foreach ($this->orderedContentTypes as $contentType) {
                $this->contentTypeArrayByName[$contentType->getName()] = $contentType;
            }
        }
    }

    public function persist(ContentType $contentType)
    {
        $em = $this->doctrine->getManager();
        $em->persist($contentType);
        $em->flush();
    }

    public function persistField(FieldType $fieldType)
    {
        $em = $this->doctrine->getManager();
        $em->persist($fieldType);
        $em->flush();
    }
    
    private function listAllFields(FieldType $fieldType)
    {
        $out = [];
        foreach ($fieldType->getChildren() as $child) {
            $out = array_merge($out, $this->listAllFields($child));
        }
        $out['key_' . $fieldType->getId()] = $fieldType;
        return $out;
    }
    
    private function reorderFieldsRecu(FieldType $fieldType, array $newStructure, array $ids)
    {
        
        $fieldType->getChildren()->clear();
        foreach ($newStructure as $key => $item) {
            if (array_key_exists('key_' . $item['id'], $ids)) {
                $fieldType->getChildren()->add($ids['key_' . $item['id']]);
                $ids['key_' . $item['id']]->setParent($fieldType);
                $ids['key_' . $item['id']]->setOrderKey($key);
                $this->reorderFieldsRecu($ids['key_' . $item['id']], isset($item['children']) ? $item['children'] : [], $ids);
            } else {
                $this->logger->warning('service.contenttype.field_not_found', [
                    'field_id' => $item['id'],
                ]);
            }
        }
    }
    
    public function reorderFields(ContentType $contentType, array $newStructure)
    {
        $em = $this->doctrine->getManager();
        
        $ids = $this->listAllFields($contentType->getFieldType());
        $this->reorderFieldsRecu($contentType->getFieldType(), $newStructure, $ids);
        
        $em->persist($contentType);
        $em->flush();

        $this->logger->notice('service.contenttype.reordered', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
        ]);
    }
    
    private function generatePipeline(FieldType $fieldType)
    {
        
        $pipelines = [];
        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if (!$child->getDeleted()) {
                /** @var DataFieldType $dataFieldType */
                $dataFieldType = $this->formRegistry->getType($child->getType())->getInnerType();
                $pipeline = $dataFieldType->generatePipeline($child);
                if ($pipeline) {
                    $pipelines[] = $pipeline;
                }
                
                if ($dataFieldType->isContainer()) {
                    $pipelines = array_merge($pipelines, $this->generatePipeline($child));
                }
            }
        }
        return $pipelines;
    }

    public function setSingleTypeIndex(Environment $environment, ContentType $contentType, string $name)
    {
        $em = $this->doctrine->getManager();
        /** @var SingleTypeIndexRepository $repository*/
        $repository = $em->getRepository('EMSCoreBundle:SingleTypeIndex');
        $repository->setIndexName($environment, $contentType, $name);
    }

    public function getIndex(ContentType $contentType, Environment $environment = null)
    {
        if (!$environment) {
            $environment = $contentType->getEnvironment();
        }

        if ($this->singleTypeIndex) {
            $em = $this->doctrine->getManager();
            /** @var SingleTypeIndexRepository $repository*/
            $repository = $em->getRepository('EMSCoreBundle:SingleTypeIndex');

            /** @var SingleTypeIndex $singleTypeIndex*/
            $singleTypeIndex = $repository->getIndexName($contentType, $environment);
            return $singleTypeIndex->getName();
        }
        return $environment->getAlias();
    }
    
    public function updateMapping(ContentType $contentType, $envs = false)
    {



        $contentType->setHavePipelines(false);
        try {
            if (!empty($contentType->getFieldType())) {
                $pipelines = $this->generatePipeline($contentType->getFieldType());
                if (!empty($pipelines)) {
                    $body = [
                            "description" => "Extract attachment information for the content type " . $contentType->getName(),
                            "processors" => $pipelines,
                    ];
                    $this->client->ingest()->putPipeline([
                            'id' => $this->instanceId . $contentType->getName(),
                            'body' => $body
                    ]);
                    $contentType->setHavePipelines(true);

                    $this->logger->notice('service.contenttype.pipelines', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    ]);
                }
            }
        } catch (BadRequest400Exception $e) {
            $contentType->setHavePipelines(false);
            $message = json_decode($e->getMessage(), true);
            if (!empty($e->getPrevious())) {
                $message = json_decode($e->getPrevious()->getMessage(), true);
            }

            $this->logger->error('service.contenttype.pipelines_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                'elasticsearch_error_type' => $message['error']['type'],
                'elasticsearch_error_reason' => $message['error']['reason'],
            ]);
        }

        try {
            $body = $this->environmentService->getIndexAnalysisConfiguration();
            if (!$envs) {
                $envs = array_reduce($this->environmentService->getManagedEnvironement(), function ($envs, $item) use ($contentType, $body) {
                    /**@var Environment $item*/
                    try {
                        $index = $this->getIndex($contentType, $item);
                    } catch (NoResultException $e) {
                        $index = $this->environmentService->getNewIndexName($item, $contentType);
                        $this->setSingleTypeIndex($item, $contentType, $index);
                    }

                    $indexExist = $this->client->indices()->exists(['index' => $index]);

                    if (!$indexExist) {
                        $this->client->indices()->create([
                            'index' => $index,
                            'body' => $body,
                        ]);

                        $this->client->indices()->putAlias([
                            'index' => $index,
                            'name' => $item->getAlias(),
                        ]);
                    }

                    if (isset($envs)) {
                        $envs .= ',' . $index;
                    } else {
                        $envs = $index;
                    }
                    return $envs;
                });
            }

            $body = $this->mappingService->generateMapping($contentType, $contentType->getHavePipelines());
            if (isset($envs)) {
                $out = $this->client->indices()->putMapping([
                    'index' => $envs,
                    'type' => $contentType->getName(),
                    'body' => $body
                ]);
                if (isset($out ['acknowledged']) && $out ['acknowledged']) {
                    $contentType->setDirty(false);
                    $this->logger->notice('service.contenttype.mappings_updated', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        'environments' => $envs,
                    ]);
                } else {
                    $contentType->setDirty(true);
                    $this->logger->warning('service.contenttype.mappings_error', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        'environments' => $envs,
                        'elasticsearch_dump' => print_r($out, true),
                    ]);
                }
            }


            $em = $this->doctrine->getManager();
            $em->persist($contentType);
            $em->flush();
        } catch (BadRequest400Exception $e) {
            $contentType->setDirty(true);
            $message = json_decode($e->getMessage(), true);
            if (!empty($e->getPrevious())) {
                $message = json_decode($e->getPrevious()->getMessage(), true);
            }

            $this->logger->error('service.contenttype.should_reindex', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'environments' => $envs,
                'elasticsearch_error_type' => $message['error']['type'],
                'elasticsearch_error_reason' => $message['error']['reason'],
            ]);
        }
    }
    
    /**
     * @param string $name
     *
     * @return ContentType|false
     */
    public function getByName($name)
    {
        $this->loadEnvironment();
        return $this->contentTypeArrayByName[$name] ?? false;
    }

    /**
     * @return array
     */
    public function getAllByAliases()
    {
        $this->loadEnvironment();
        $contentTypeAliases = [];
        /**@var ContentType $contentType */
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($contentTypeAliases[$contentType->getEnvironment()->getAlias()])) {
                $contentTypeAliases[$contentType->getEnvironment()->getAlias()] = [];
            }
            $contentTypeAliases[$contentType->getEnvironment()->getAlias()][$contentType->getName()] = $contentType;
        }
        return $contentTypeAliases;
    }
    

    public function getAllDefaultEnvironmentNames()
    {
        $this->loadEnvironment();
        $out = [];
        /**@var ContentType $contentType */
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->getEnvironment()->getAlias()])) {
                $out[$contentType->getEnvironment()->getName()] = $contentType->getEnvironment()->getName();
            }
        }
        return array_keys($out);
    }

    public function getAllAliases()
    {
        $this->loadEnvironment();
        $out = [];
        /**@var ContentType $contentType */
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->getEnvironment()->getAlias()])) {
                $out[$contentType->getEnvironment()->getAlias()] = $contentType->getEnvironment()->getAlias();
            }
        }
        return implode(',', $out);
    }

    public function getAll()
    {
        $this->loadEnvironment();
        return $this->orderedContentTypes;
    }

    public function getAllNames()
    {
        $this->loadEnvironment();
        $out = [];
        /**@var Environment $env*/
        foreach ($this->orderedContentTypes as $env) {
            $out[] = $env->getName();
        }
        return $out;
    }

    /**
     * @return string
     */
    public function getAllTypes()
    {
        $this->loadEnvironment();
        return implode(',', array_keys($this->contentTypeArrayByName));
    }


    public function updateFromJson(ContentType $contentType, string $json, bool $isDeleteExitingTemplates, bool $isDeleteExitingViews): void
    {
        $this->deleteFields($contentType);
        if ($isDeleteExitingTemplates) {
            $this->deleteTemplates($contentType);
        }
        if ($isDeleteExitingViews) {
            $this->deleteViews($contentType);
        }

        $environment = $contentType->getEnvironment();
        if (! $environment instanceof Environment) {
            throw new NotFoundHttpException('Environment not found');
        }

        $updatedContentType = $this->contentTypeFromJson($json, $environment, $contentType);
        $this->importContentType($updatedContentType);
    }

    public function contentTypeFromJson(string $json, Environment $environment, ContentType $contentType = null): ContentType
    {
        $meta = JsonClass::fromJsonString($json);
        $contentType = $meta->jsonDeserialize($contentType);
        if (!$contentType instanceof ContentType) {
            throw new \Exception(sprintf('ContentType expected for import, got %s', $meta->getClass()));
        }
        $contentType->setEnvironment($environment);

        return $contentType;
    }

    private function deleteFields(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        $contentType->unsetFieldType();
        /** @var FieldTypeRepository $fieldRepo */
        $fieldRepo = $em->getRepository('EMSCoreBundle:FieldType');
        $fields = $fieldRepo->findBy([
            'contentType' => $contentType
        ]);
        foreach ($fields as $field) {
            $em->remove($field);
        }
        $em->flush();
    }

    private function deleteTemplates(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        foreach ($contentType->getTemplates() as $template) {
            $contentType->removeTemplate($template);
        }
        /** @var TemplateRepository $templateRepo */
        $templateRepo = $em->getRepository('EMSCoreBundle:Template');
        $templates = $templateRepo->findBy([
            'contentType' => $contentType
        ]);
        foreach ($templates as $template) {
            $em->remove($template);
        }

        $em->flush();
    }

    private function deleteViews(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        foreach ($contentType->getViews() as $view) {
            $contentType->removeView($view);
        }
        /** @var ViewRepository $viewRepo */
        $viewRepo = $em->getRepository('EMSCoreBundle:View');
        $views = $viewRepo->findBy([
            'contentType' => $contentType
        ]);
        foreach ($views as $view) {
            $em->remove($view);
        }

        $em->flush();
    }

    public function importContentType(ContentType $contentType): ContentType
    {
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');

        $previousContentType =  $this->getByName($contentType->getName());
        if ($previousContentType instanceof ContentType && $previousContentType->getId() !== $contentType->getId()) {
            throw new ContentTypeAlreadyExistException('ContentType with name ' . $contentType->getName() . ' already exists');
        }

        $contentType->reset($contentTypeRepository->nextOrderKey());
        $this->persist($contentType);
        return $contentType;
    }
}

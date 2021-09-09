<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Exception\ContentTypeAlreadyExistException;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface;

class ContentTypeService
{
    /** @var string */
    private const CONTENT_TYPE_AGGREGATION_NAME = 'content-types';

    /** @var Registry */
    protected $doctrine;
    /** @var LoggerInterface */
    protected $logger;
    /** @var Mapping */
    private $mappingService;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var FormRegistryInterface */
    private $formRegistry;
    /** @var TranslatorInterface */
    private $translator;
    /** @var string */
    private $instanceId;
    /** @var ContentType[] */
    protected $orderedContentTypes = [];
    /** @var ContentType[] */
    protected $contentTypeArrayByName = [];

    public function __construct(Registry $doctrine, LoggerInterface $logger, Mapping $mappingService, ElasticaService $elasticaService, EnvironmentService $environmentService, FormRegistryInterface $formRegistry, TranslatorInterface $translator, $instanceId)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->mappingService = $mappingService;
        $this->elasticaService = $elasticaService;
        $this->environmentService = $environmentService;
        $this->formRegistry = $formRegistry;
        $this->instanceId = $instanceId;
        $this->translator = $translator;
    }

    /**
     * Get child by path.
     *
     * @param string $path
     * @param bool   $skipVirtualFields
     *
     * @return FieldType|false
     */
    public function getChildByPath(FieldType $fieldType, $path, $skipVirtualFields = false)
    {
        $elem = \explode('.', $path);
        if (!empty($elem)) {
            /** @var FieldType $child */
            foreach ($fieldType->getChildren() as $child) {
                if (!$child->getDeleted()) {
                    $type = $child->getType();
                    if ($skipVirtualFields && $type::isVirtual($child->getOptions())) {
                        $fieldTypeByPath = $this->getChildByPath($child, $path, $skipVirtualFields);
                        if ($fieldTypeByPath) {
                            return $fieldTypeByPath;
                        }
                    } elseif ($child->getName() == $elem[0]) {
                        if (\strpos($path, '.')) {
                            $fieldTypeByPath = $this->getChildByPath($fieldType, \substr($path, \strpos($path, '.') + 1), $skipVirtualFields);
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
        if ([] === $this->orderedContentTypes) {
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
            $out = \array_merge($out, $this->listAllFields($child));
        }
        $out['key_'.$fieldType->getId()] = $fieldType;

        return $out;
    }

    private function reorderFieldsRecu(FieldType $fieldType, array $newStructure, array $ids)
    {
        $fieldType->getChildren()->clear();
        foreach ($newStructure as $key => $item) {
            if (\array_key_exists('key_'.$item['id'], $ids)) {
                $fieldType->getChildren()->add($ids['key_'.$item['id']]);
                $ids['key_'.$item['id']]->setParent($fieldType);
                $ids['key_'.$item['id']]->setOrderKey($key);
                $this->reorderFieldsRecu($ids['key_'.$item['id']], isset($item['children']) ? $item['children'] : [], $ids);
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

    public function getIndex(ContentType $contentType, Environment $environment = null): string
    {
        if (!$environment) {
            $environment = $contentType->getEnvironment();
        }

        return $environment->getAlias();
    }

    public function updateMapping(ContentType $contentType, $envs = false)
    {
        $contentType->setHavePipelines(false);

        try {
            $body = $this->environmentService->getIndexAnalysisConfiguration();
            if (!$envs) {
                $envs = \array_reduce($this->environmentService->getManagedEnvironement(), function ($envs, $item) use ($contentType, $body) {
                    /* @var Environment $item */
                    $index = $this->getIndex($contentType, $item);
                    $this->mappingService->createIndex($index, $body, $item->getAlias());

                    if (isset($envs)) {
                        $envs .= ','.$index;
                    } else {
                        $envs = $index;
                    }

                    return $envs;
                });
            }

            if (isset($envs)) {
                if ($this->mappingService->putMapping($contentType, $envs)) {
                    $contentType->setDirty(false);
                } else {
                    $contentType->setDirty(true);
                }
            }

            $em = $this->doctrine->getManager();
            $em->persist($contentType);
            $em->flush();
        } catch (BadRequest400Exception $e) {
            $contentType->setDirty(true);
            $message = \json_decode($e->getMessage(), true);
            if (!empty($e->getPrevious())) {
                $message = \json_decode($e->getPrevious()->getMessage(), true);
            }

            $this->logger->error('service.contenttype.should_reindex', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'environments' => $envs,
                'elasticsearch_error_type' => $message['error']['type'],
                'elasticsearch_error_reason' => $message['error']['reason'],
            ]);
        }
    }

    public function giveByName(string $name): ContentType
    {
        $this->loadEnvironment();

        $contentType = $this->contentTypeArrayByName[$name] ?? false;

        if (!$contentType) {
            throw new \RuntimeException(\sprintf('Could not find contentType with name %s', $name));
        }

        return $contentType;
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
        /** @var ContentType $contentType */
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
        /** @var ContentType $contentType */
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->getEnvironment()->getAlias()])) {
                $out[$contentType->getEnvironment()->getName()] = $contentType->getEnvironment()->getName();
            }
        }

        return \array_keys($out);
    }

    public function getAllAliases()
    {
        $this->loadEnvironment();
        $out = [];
        /** @var ContentType $contentType */
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->getEnvironment()->getAlias()])) {
                $out[$contentType->getEnvironment()->getAlias()] = $contentType->getEnvironment()->getAlias();
            }
        }

        return \implode(',', $out);
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
        /** @var Environment $env */
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

        return \implode(',', \array_keys($this->contentTypeArrayByName));
    }

    public function updateFromJson(ContentType $contentType, string $json, bool $isDeleteExitingTemplates, bool $isDeleteExitingViews): ContentType
    {
        $this->deleteFields($contentType);
        if ($isDeleteExitingTemplates) {
            $this->deleteTemplates($contentType);
        }
        if ($isDeleteExitingViews) {
            $this->deleteViews($contentType);
        }

        $environment = $contentType->getEnvironment();
        if (!$environment instanceof Environment) {
            throw new NotFoundHttpException('Environment not found');
        }

        $updatedContentType = $this->contentTypeFromJson($json, $environment, $contentType);

        return $this->importContentType($updatedContentType);
    }

    public function contentTypeFromJson(string $json, Environment $environment, ContentType $contentType = null): ContentType
    {
        $meta = JsonClass::fromJsonString($json);
        $contentType = $meta->jsonDeserialize($contentType);
        if (!$contentType instanceof ContentType) {
            throw new \Exception(\sprintf('ContentType expected for import, got %s', $meta->getClass()));
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
            'contentType' => $contentType,
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
            'contentType' => $contentType,
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
            'contentType' => $contentType,
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

        $previousContentType = $this->getByName($contentType->getName());
        if ($previousContentType instanceof ContentType && $previousContentType->getId() !== $contentType->getId()) {
            throw new ContentTypeAlreadyExistException('ContentType with name '.$contentType->getName().' already exists');
        }

        $contentType->reset($contentTypeRepository->nextOrderKey());
        $this->persist($contentType);

        return $contentType;
    }

    /**
     * @return array<array{name: string, alias: string, envId: int, count: int}>
     */
    public function getUnreferencedContentTypes(): array
    {
        $unreferencedContentTypes = [];
        foreach ($this->environmentService->getUnmanagedEnvironments() as $environment) {
            try {
                $unreferencedContentTypes = \array_merge($unreferencedContentTypes, $this->getUnreferencedContentTypesPerEnvironment($environment));
            } catch (\Throwable $e) {
                $this->logger->error('log.service.content-type.get-unreferenced-content-type.unexpected-error', [
                    EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                ]);
            }
        }

        return $unreferencedContentTypes;
    }

    /**
     * @return array<array{name: string, alias: string, envId: int, count: int}>
     */
    private function getUnreferencedContentTypesPerEnvironment(Environment $environment): array
    {
        $search = new Search([$environment->getAlias()]);
        $search->setSize(0);
        $search->addTermsAggregation(self::CONTENT_TYPE_AGGREGATION_NAME, EMSSource::FIELD_CONTENT_TYPE, 30);
        $resultSet = $this->elasticaService->search($search);
        $contentTypeNames = $resultSet->getAggregation(self::CONTENT_TYPE_AGGREGATION_NAME)['buckets'] ?? [];
        $unreferencedContentTypes = [];
        foreach ($contentTypeNames as $contentTypeName) {
            $name = $contentTypeName['key'] ?? null;
            if (null !== $name && false === $this->getByName($name)) {
                $unreferencedContentTypes[] = [
                    'name' => $name,
                    'alias' => $environment->getAlias(),
                    'envId' => $environment->getId(),
                    'count' => \intval($contentTypeName['doc_count'] ?? 0),
                ];
            }
        }

        return $unreferencedContentTypes;
    }

    public function update(ContentType $contentType, bool $mustBeReset = true): void
    {
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        if ($mustBeReset) {
            $contentType->reset($contentTypeRepository->nextOrderKey());
        }
        $this->persist($contentType);
        $em->flush();
    }
}

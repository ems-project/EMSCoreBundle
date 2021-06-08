<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformContext;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Service\Revision\LoggingContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;

class TransformContentTypeService
{
    private LoggerInterface $logger;
    private ContentTypeService $contentTypeService;
    private DataService $dataService;
    private FormFactoryInterface $formFactory;
    private ElasticaService $elasticaService;

    private const DEFAULT_SCROLL_SIZE = 100;

    public function __construct(
        LoggerInterface $logger,
        ElasticaService $elasticaService,
        ContentTypeService $contentTypeService,
        DataService $dataService,
        FormFactoryInterface $formFactory
    ) {
        $this->logger = $logger;
        $this->elasticaService = $elasticaService;
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
    }

    public function transform(ContentType $contentType, string $user): \Generator
    {
        $search = $this->getSearch($contentType);
        $scroll = $this->elasticaService->scroll($search, '10m');

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if (false === $result) {
                    continue;
                }
                $isChanged = false;
                $ouuid = $result->getId();
                $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);

                if ($revision->getDraft()) {
                    $this->logger->warning('service.data.transform_content_type.cant_process_draft', LoggingContext::read($revision));
                    yield $revision;
                    continue;
                }

                $revisionType = $this->formFactory->create(RevisionType::class, $revision);
                $result = $this->dataService->walkRecursive($revisionType->get('data'), $result->getSource(), function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) use (&$isChanged) {
                    if (null === $data) {
                        return [];
                    }
                    if ($dataFieldType->isVirtual()) {
                        return $data;
                    }

                    $transformer = $this->getTransformer($dataField);
                    $contentTransformContext = ContentTransformContext::fromDataFieldType(\get_class($dataFieldType), $data);
                    if (!empty($transformer) && $transformer->canTransform($contentTransformContext)) {
                        $dataTransformed = $transformer->transform($contentTransformContext);
                        $contentTransformContext->setTransformedData($dataTransformed);
                        if ($transformer->hasChanges($contentTransformContext)) {
                            $isChanged = true;

                            return [$name => $dataTransformed];
                        }
                    }

                    return [$name => $data];
                });

                if (!$isChanged) {
                    yield $revision;
                    continue;
                }

                try {
                    $revision = $this->dataService->initNewDraft($contentType->getName(), $ouuid, null, $user);
                    $revision->setRawData($result);
                    $this->dataService->finalizeDraft($revision, $revisionType, $user);
                } catch (\Throwable $e) {
                    $this->logger->error('service.data.transform_content_tyoe.errer_on_save',
                        \array_merge(LoggingContext::read($revision), [
                            EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                            EmsFields::LOG_EXCEPTION_FIELD => $e,
                        ])
                    );
                }
                yield $revision;
            }
        }
    }

    private function getTransformer(DataField $dataField): ?ContentTransformInterface
    {
        $transformerClass = $dataField->getFieldType()->getMigrationgOption('transformer');
        if (null === $transformerClass) {
            return null;
        }

        return new $transformerClass();
    }

    public function getTotal(ContentType $contentType): int
    {
        $search = $this->getSearch($contentType);

        return $this->elasticaService->count($search);
    }

    private function getSearch(ContentType $contentType): Search
    {
        $query = $this->elasticaService->filterByContentTypes(null, [$contentType->getName()]);
        $search = new Search([$this->contentTypeService->getIndex($contentType)], $query);
        $search->setSize(self::DEFAULT_SCROLL_SIZE);

        return $search;
    }
}

<?php

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\ContentTransformer\ContentTransformContext;
use EMS\CoreBundle\ContentTransformer\ContentTransformInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;

class TransformContentTypeService
{
    /** @var LoggerInterface */
    private $logger;

    /** @var Client */
    private $client;

    /** @var ContentTypeService */
    private $contentTypeService;

    /** @var DataService */
    private $dataService;

    /** @var FormFactoryInterface */
    private $formFactory;

    const DEFAULT_SCROLL_SIZE = 100;

    public function __construct(
        LoggerInterface $logger,
        Client $client,
        ContentTypeService $contentTypeService,
        DataService $dataService,
        FormFactoryInterface $formFactory
    ) {
        $this->logger = $logger;
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
    }

    public function transform(ContentType $contentType): \Generator
    {
        $total = $this->getTotal($contentType);
        for ($from = 0; $from < $total; $from = $from + self::DEFAULT_SCROLL_SIZE) {
            $scroll = $this->getScroll($contentType, $from);

            foreach ($scroll['hits']['hits'] as $hit) {
                $isChanged = false;
                $ouuid = $hit['_id'];
                $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);

                if ($revision->getDraft()) {
                    $this->logger->warning('service.data.transform_content_type.cant_process_draft', [
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_ENVIRONMENT_FIELD => $contentType->getEnvironment()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    ]);
                    yield $revision;
                    continue;
                }

                $revisionType = $this->formFactory->create(RevisionType::class, $revision);
                $result = $this->dataService->walkRecursive($revisionType->get('data'), $hit['_source'], function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) use (&$isChanged) {
                    if ($data === null) {
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
                    $revision = $this->dataService->initNewDraft($contentType->getName(), $ouuid, null, 'TRANSFORM_CONTENT');
                    $revision->setRawData($result);
                    $this->dataService->finalizeDraft($revision, $revisionType, 'TRANSFORM_CONTENT');
                } catch (Exception $e) {
                    $this->logger->error('service.data.transform_content_tyoe.errer_on_save', [
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                        EmsFields::LOG_ENVIRONMENT_FIELD => $contentType->getEnvironment()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                        EmsFields::LOG_EXCEPTION_FIELD => $e,
                    ]);
                }
                yield $revision;
            }
        }
    }

    private function getTransformer(DataField $dataField): ?ContentTransformInterface
    {
        $transformerClass = $dataField->getFieldType()->getMigrationgOption('transformer');
        if ($transformerClass === null) {
            return null;
        }

        return new $transformerClass();
    }

    public function getTotal(ContentType $contentType): int
    {
        $scroll = $this->getScroll($contentType);
        return $scroll['hits']['total'];
    }

    private function getScroll(ContentType $contentType, int $from = 0): array
    {
        return $this->client->search([
            'index' => $this->contentTypeService->getIndex($contentType),
            'size' => self::DEFAULT_SCROLL_SIZE,
            'from' => $from,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['_type' => $contentType->getName()]],
                            ['term' => ['_contenttype' => $contentType->getName()]],
                        ],
                    ],
                ]
            ]
        ]);
    }
}

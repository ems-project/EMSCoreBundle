<?php

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use EMS\CommonBundle\Common\Document;
use EMS\CoreBundle\ContentTransformer\ContentTransformContext;
use EMS\CoreBundle\ContentTransformer\ContentTransformInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Form\RevisionType;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

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

    /** @var int */
    private $chunk;

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
        $this->chunk = 100;
    }

    public function transform(ContentType $contentType): \Generator
    {
        $total = $this->getTotal($contentType);
        for ($from = 0; $from < $total; $from = $from + $this->chunk) {
            $scroll = $this->getScroll($contentType, $from);

            foreach ($scroll['hits']['hits'] as &$hit) {
                $isChanged = false;
                $document = new Document($contentType->getName(), $hit['_id'], $hit['_source']);

                $revision = $this->dataService->initNewDraft($document->getContentType(), $document->getOuuid(), null, 'TRANSFORM_CONTENT');
                $revisionType = $this->formFactory->create(RevisionType::class, $revision);

                $result = $this->dataService->walkRecursive($revisionType->get('data'), $hit['_source'], function (string $name, $data, DataFieldType $dataFieldType, DataField $dataField) use (&$isChanged) {
                    if($data === null) {
                        return [];
                    }

                    $transformer = $this->getTransformer($dataField);
                    if (!empty($transformer) && $transformer->canTransform(new ContentTransformContext([$dataFieldType]))) {
                        $dataTransformed = $transformer->transform($data);
                        if ($transformer->changed($dataTransformed)) {
                            $isChanged = true;
                            return [$name => $dataTransformed];
                        }
                    }

                    return [$name => $data];
                });

                if (!$isChanged) {
                    $this->dataService->discardDraft($revision, false, 'TRANSFORM_CONTENT');
                    yield $document;
                    continue;
                }

                $data = $revision->getRawData();

                foreach ($result['data'] as $key => $value) {
                    $data[$key] = $value;
                }

                $revision->setRawData($data);

                $this->dataService->finalizeDraft($revision, $revisionType, 'TRANSFORM_CONTENT');
                yield $document;
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
            'size' => $this->chunk,
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

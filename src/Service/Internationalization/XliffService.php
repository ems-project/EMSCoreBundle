<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class XliffService
{
    private LoggerInterface $logger;
    private RevisionService $revisionService;
    private ElasticaService $elasticaService;

    public function __construct(LoggerInterface $logger, RevisionService $revisionService, ElasticaService $elasticaService)
    {
        $this->logger = $logger;
        $this->revisionService = $revisionService;
        $this->elasticaService = $elasticaService;
    }

    /**
     * @param FieldType[] $fields
     */
    public function extract(ContentType $contentType, Document $source, Extractor $extractor, array $fields, Environment $sourceEnvironment, Environment $targetEnvironment): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $sourceRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $sourceEnvironment);
        $currentRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $targetEnvironment);

        if (null === $sourceRevision) {
            throw new \RuntimeException('Unexpected null revision');
        }
        $sourceData = $sourceRevision->getRawData();

        if ($currentRevision instanceof Revision) {
            $currentData = $currentRevision->getRawData();
        } else {
            $currentData = [];
        }

        $xliffDoc = $extractor->addDocument($contentType->getName(), $source->getId(), \strval($sourceRevision->getId()));
        foreach ($fields as $fieldPath => $field) {
            $propertyPath = Document::fieldPathToPropertyPath($fieldPath);
            $value = $propertyAccessor->getValue($sourceData, $propertyPath);
            if (null === $value) {
                continue;
            }
            $currentValue = $propertyAccessor->getValue($currentData, $propertyPath);
            if ($currentValue === $value) {
                //TODO: get the current translation in order to flag it as already OK;
            }

            if ($this->isHtml($value)) {
                $extractor->addHtmlField($xliffDoc, $fieldPath, $value, $propertyAccessor->getValue($currentData, $propertyPath));
            } else {
                $extractor->addSimpleField($xliffDoc, $fieldPath, $value, $propertyAccessor->getValue($currentData, $propertyPath));
            }
        }
    }

    /**
     * @param string[] $fields
     *
     * @throws XliffException
     */
    private function getTargetRevision(Document $source, Extractor $extractor, array $fields, ContentType $targetContentType, Environment $targetEnvironment, ?string $sourceDocumentField): ?Revision
    {
        if (null === $sourceDocumentField) {
            return $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $targetContentType, $targetEnvironment) ?? null;
        }

        $search = $this->elasticaService->generateTermsSearch([$targetEnvironment->getAlias()], $sourceDocumentField, [$source->getId()], [$targetContentType->getName()]);
        $resultSet = $this->elasticaService->search($search);
        $response = Response::fromResultSet($resultSet);

        if (0 === $response->getTotal()) {
            return $this->revisionService->generate();
        }
        if (1 !== $response->getTotal()) {
            throw new XliffException(\sprintf('Multiple targets found for the source %s', $source->getId()));
        }

        foreach ($response->getDocuments() as $document) {
            $revision = $this->revisionService->getCurrentRevisionForEnvironment($document->getId(), $targetContentType, $targetEnvironment);
            if (null === $revision) {
                break;
            }

            return $revision;
        }

        throw new XliffException(\sprintf('Target for source %s found in elasticsearch but not in DB', $source->getId()));
    }

    private function isHtml(string $value): bool
    {
        return 1 === \preg_match('/^<([A-Za-z][A-Za-z0-9]*)\b[^>]*>(.*?)<(\/[A-Za-z][A-Za-z0-9]*\s*|[A-Za-z][A-Za-z0-9]*\s*\/)>$/', \trim($value));
    }
}

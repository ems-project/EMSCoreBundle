<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use Elastica\Query\Terms;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
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
    public function extract(ContentType $contentType, Document $source, Extractor $extractor, array $fields, Environment $sourceEnvironment, ?Environment $targetEnvironment, string $targetLocale, string $localeField, string $translationField): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $sourceRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $sourceEnvironment);
        $currentData = [];
        if (null !== $targetEnvironment) {
            $currentRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $targetEnvironment);
            $currentData = null === $currentRevision ? [] : $currentRevision->getRawData();
        }

        if (null === $sourceRevision) {
            throw new \RuntimeException('Unexpected null revision');
        }
        $sourceData = $sourceRevision->getRawData();

        $translationId = $propertyAccessor->getValue($currentData, Document::fieldPathToPropertyPath($translationField));
        if (null !== $targetEnvironment && null !== $translationId) {
            $currentTranslationData = $this->getCurrentTranslationData($targetEnvironment, $translationField, $translationId, $localeField, $targetLocale);
        } else {
            $currentTranslationData = [];
        }

        $xliffDoc = $extractor->addDocument($contentType->getName(), $source->getId(), \strval($sourceRevision->getId()));
        foreach ($fields as $fieldPath => $field) {
            $propertyPath = Document::fieldPathToPropertyPath($fieldPath);
            $value = $propertyAccessor->getValue($sourceData, $propertyPath);
            if (null === $value) {
                continue;
            }
            $currentValue = $propertyAccessor->getValue($currentData, $propertyPath);
            $translation = $propertyAccessor->getValue($currentTranslationData, $propertyPath);
            $isFinal = ($currentValue === $value && null !== $translation);

            if ($this->isHtml($value)) {
                $extractor->addHtmlField($xliffDoc, $fieldPath, $value, $translation, $isFinal);
            } else {
                $extractor->addSimpleField($xliffDoc, $fieldPath, $value, $translation, $isFinal);
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

    /**
     * @return array<mixed>
     */
    private function getCurrentTranslationData(Environment $targetEnvironment, string $translationField, string $translationId, string $localeField, string $targetLocale): array
    {
        $boolQuery = $this->elasticaService->getBoolQuery();
        $boolQuery->addMust(new Terms($translationField, [$translationId]));
        $boolQuery->addMust(new Terms($localeField, [$targetLocale]));
        $search = new Search([$targetEnvironment->getAlias()], $boolQuery);
        $this->elasticaService->getTermsQuery($translationField, [$translationId]);
        try {
            $resultSet = $this->elasticaService->singleSearch($search);

            return $resultSet->getSource();
        } catch (NotSingleResultException $e) {
            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use Elastica\Query\Terms;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use EMS\CoreBundle\Helper\Xliff\InsertionRevision;
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

    public function insert(InsertionRevision $insertionRevision, string $localeField, string $translationField, ?Environment $publishAndArchive): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($insertionRevision->getRevisionId());
        $targetLocale = $insertionRevision->getTargetLocale();
        $target = $this->getTargetDocument(
            $publishAndArchive ?? $revision->giveContentType()->giveEnvironment(),
            $revision,
            $targetLocale,
            $localeField,
            $translationField
        );

        $data = $revision->getRawData();
        $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($translationField), $targetLocale);
        $insertionRevision->extractTranslations($data, $data);

        \dump($data);
        //TODO: Init draft
        //TODO:Set Raw, deleted and archive
        //TODO: Finalise
        //TODO:Return Revision
    }

    private function getTargetDocument(Environment $environment, Revision $revision, string $targetLocale, string $localeField, string $translationField): ?Document
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $translationId = $propertyAccessor->getValue($revision->getRawData(), Document::fieldPathToPropertyPath($translationField));
        if (!\is_string($translationId)) {
            throw new \RuntimeException('Translation ID not found');
        }

        return $this->getCurrentTranslation($environment, $translationField, $translationId, $localeField, $targetLocale);
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
        $document = $this->getCurrentTranslation($targetEnvironment, $translationField, $translationId, $localeField, $targetLocale);

        return null === $document ? [] : $document->getSource();
    }

    private function getCurrentTranslation(Environment $targetEnvironment, string $translationField, string $translationId, string $localeField, string $targetLocale): ?Document
    {
        $boolQuery = $this->elasticaService->getBoolQuery();
        $boolQuery->addMust(new Terms($translationField, [$translationId]));
        $boolQuery->addMust(new Terms($localeField, [$targetLocale]));
        $search = new Search([$targetEnvironment->getAlias()], $boolQuery);
        $this->elasticaService->getTermsQuery($translationField, [$translationId]);
        try {
            return $this->elasticaService->singleSearch($search);
        } catch (NotSingleResultException $e) {
            return null;
        }
    }
}

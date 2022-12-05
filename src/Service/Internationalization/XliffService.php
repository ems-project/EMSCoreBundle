<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use Doctrine\ORM\UnexpectedResultException;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Html\Html;
use EMS\Xliff\Xliff\Entity\InsertReport;
use EMS\Xliff\Xliff\Extractor;
use EMS\Xliff\Xliff\InsertionRevision;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

class XliffService
{
    public function __construct(private readonly LoggerInterface $logger, private readonly RevisionService $revisionService, private readonly ElasticaService $elasticaService)
    {
    }

    /**
     * @param FieldType[] $fields
     */
    public function extract(ContentType $contentType, Document $source, Extractor $extractor, array $fields, Environment $sourceEnvironment, ?Environment $targetEnvironment, string $targetLocale, string $localeField, string $translationField, bool $encodeHtml): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $sourceRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $sourceEnvironment);
        $currentData = [];
        if (null !== $targetEnvironment) {
            try {
                $currentRevision = $this->revisionService->getCurrentRevisionForEnvironment($source->getId(), $contentType, $targetEnvironment);
                $currentData = null === $currentRevision ? [] : $currentRevision->getRawData();
            } catch (UnexpectedResultException) {
                $currentData = [];
            }
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
            $isFinal = (null !== $targetEnvironment && $contentType->giveEnvironment()->getName() !== $targetEnvironment->getName() && $currentValue === $value && null !== $translation);

            if (Html::isHtml($value)) {
                $extractor->addHtmlField($xliffDoc, $fieldPath, $value, $translation, $isFinal, $encodeHtml);
            } else {
                $extractor->addSimpleField($xliffDoc, $fieldPath, $value, $translation, $isFinal);
            }
        }
    }

    public function insert(InsertReport $insertReport, InsertionRevision $insertionRevision, string $localeField, string $translationField, ?Environment $publishAndArchive, string $username = null): Revision
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
        $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($localeField), $targetLocale);
        $insertionRevision->extractTranslations($insertReport, $data, $data);

        if (null === $target) {
            $currentRevision = $this->revisionService->create($revision->giveContentType(), null, [], $username);
        } else {
            $currentRevision = $this->revisionService->getCurrentRevisionForDocument($target);
            if (null === $currentRevision) {
                throw new \RuntimeException(\sprintf('A document %s exist but not the current revision', $target->getId()));
            }
        }

        return $this->revisionService->updateRawData($currentRevision, $data, $username);
    }

    public function testInsert(InsertReport $insertReport, InsertionRevision $insertionRevision, string $localeField): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($insertionRevision->getRevisionId());
        $targetLocale = $insertionRevision->getTargetLocale();

        $data = $revision->getRawData();
        $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($localeField), $targetLocale);
        $insertionRevision->extractTranslations($insertReport, $data, $data);
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
        $boolQuery->addMust($this->elasticaService->getTermsQuery($translationField, [$translationId]));
        $boolQuery->addMust($this->elasticaService->getTermsQuery($localeField, [$targetLocale]));
        $search = new Search([$targetEnvironment->getAlias()], $boolQuery);
        try {
            return $this->elasticaService->singleSearch($search);
        } catch (NotSingleResultException $e) {
            if ($e->getTotal() > 1) {
                $this->logger->warning('log.service.xliff.to-many-current-translations', [
                    'counter' => $e->getTotal(),
                    'environment' => $targetEnvironment->getName(),
                    'translationField' => $translationField,
                    'translationId' => $translationId,
                    'localeField' => $localeField,
                    'targetLocale' => $targetLocale,
                ]);
            }

            return null;
        }
    }
}

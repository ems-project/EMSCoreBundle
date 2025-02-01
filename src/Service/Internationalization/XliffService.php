<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use Doctrine\ORM\UnexpectedResultException;
use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Exception\NotSingleResultException;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Xliff\Xliff\Entity\InsertReport;
use EMS\Xliff\Xliff\Extractor;
use EMS\Xliff\Xliff\InsertionRevision;
use Psr\Log\LoggerInterface;

class XliffService
{
    public function __construct(private readonly LoggerInterface $logger, private readonly RevisionService $revisionService, private readonly ElasticaService $elasticaService)
    {
    }

    /**
     * @param string[] $fields
     */
    public function extract(ContentType $contentType, Document $source, Extractor $extractor, array $fields, Environment $sourceEnvironment, ?Environment $targetEnvironment, string $targetLocale, ?string $localeField, ?string $translationField, bool $withBaseline): void
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();

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

        if (null !== $translationField) {
            $translationId = $propertyAccessor->getValue($currentData, Document::fieldPathToPropertyPath($translationField));
        }
        if (null !== $localeField && null !== $translationField && null !== $targetEnvironment && null !== $translationId) {
            $currentTranslationData = $this->getCurrentTranslationData($targetEnvironment, $translationField, $translationId, $localeField, $targetLocale);
        } else {
            $currentTranslationData = (null === $localeField ? $currentData : []);
        }
        if (null !== $localeField && null !== $translationField && $withBaseline && null !== $targetEnvironment && null !== $translationId) {
            $baselineTranslationData = $this->getCurrentTranslationData($targetEnvironment, $translationField, $translationId, $localeField, $extractor->getSourceLocale());
        } else {
            $baselineTranslationData = (null === $localeField && $withBaseline ? $sourceData : []);
        }

        $xliffDoc = $extractor->addDocument($contentType->getName(), $source->getId(), (string) $sourceRevision->getId());
        foreach ($fields as $fieldPath) {
            $propertyPath = Document::fieldPathToPropertyPath($fieldPath);
            foreach ($propertyAccessor->iterator($propertyPath, $sourceData, [InsertionRevision::LOCALE_PLACE_HOLDER => $extractor->getSourceLocale()]) as $path => $value) {
                $sourcePath = \str_replace(InsertionRevision::LOCALE_PLACE_HOLDER, $extractor->getSourceLocale(), $path);
                $targetPath = \str_replace(InsertionRevision::LOCALE_PLACE_HOLDER, $targetLocale, $path);
                $currentValue = $propertyAccessor->getValue($currentData, $sourcePath);
                $translation = $propertyAccessor->getValue($currentTranslationData, $targetPath);
                $baseline = $propertyAccessor->getValue($baselineTranslationData, $targetPath);
                $isFinal = (null !== $targetEnvironment && $contentType->giveEnvironment()->getName() !== $targetEnvironment->getName() && $currentValue === $value && (null !== $translation || '' === $value));

                if (HtmlHelper::isHtml($value)) {
                    $extractor->addHtmlField($xliffDoc, $path, $value, $translation, $baseline, $isFinal);
                } else {
                    $extractor->addSimpleField($xliffDoc, $path, $value, $translation, $isFinal);
                }
            }
        }
    }

    public function insert(InsertReport $insertReport, InsertionRevision $insertionRevision, ?string $localeField, ?string $translationField, ?Environment $publishAndArchive, ?string $username = null, bool $currentRevisionOnly = false): Revision
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($insertionRevision->getRevisionId());
        if ($currentRevisionOnly && !$revision->isCurrent()) {
            $this->logger->warning('log.service.xliff.not_current_revision', [
                'revision_id' => $insertionRevision->getRevisionId(),
                'ouuid' => $revision->giveOuuid(),
            ]);
            throw new XliffException($insertionRevision, 'The source revision is not more the current revision of the document');
        }
        $targetLocale = $insertionRevision->getTargetLocale();
        if (null !== $translationField && null !== $localeField) {
            $target = $this->getTargetDocument(
                $publishAndArchive ?? $revision->giveContentType()->giveEnvironment(),
                $revision,
                $targetLocale,
                $localeField,
                $translationField
            );
        } else {
            $target = $this->elasticaService->getDocument($revision->giveContentType()->giveEnvironment()->getAlias(), $revision->giveContentType()->getName(), $revision->giveOuuid());
        }

        $data = $revision->getRawData();
        if (null !== $localeField) {
            $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($localeField), $targetLocale);
        }
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

    public function testInsert(InsertReport $insertReport, InsertionRevision $insertionRevision, ?string $localeField): void
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($insertionRevision->getRevisionId());
        $targetLocale = $insertionRevision->getTargetLocale();

        $data = $revision->getRawData();
        if (null !== $localeField) {
            $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($localeField), $targetLocale);
        }
        $insertionRevision->extractTranslations($insertReport, $data, $data);
    }

    private function getTargetDocument(Environment $environment, Revision $revision, string $targetLocale, ?string $localeField, ?string $translationField): ?Document
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        if (null === $localeField || null === $translationField) {
            return $this->elasticaService->getDocument($environment->getAlias(), $revision->giveContentType()->getName(), $revision->giveOuuid());
        }

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

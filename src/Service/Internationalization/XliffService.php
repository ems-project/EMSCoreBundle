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
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Helpers\PropertyAccess\PropertyAccessor;
use EMS\Helpers\Standard\Type;
use EMS\Xliff\Model\Document as XliffDocument;
use EMS\Xliff\Model\Package;
use EMS\Xliff\Xliff;
use Psr\Log\LoggerInterface;

class XliffService
{
    public function __construct(private readonly LoggerInterface $logger, private readonly RevisionService $revisionService, private readonly ElasticaService $elasticaService)
    {
    }

    /**
     * @param string[] $fields
     */
    public function extract(ContentType $contentType, Document $source, Xliff $xliff, array $fields, Environment $sourceEnvironment, ?Environment $targetEnvironment, string $targetLocale, ?string $localeField, ?string $translationField, bool $withBaseline): void
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
            $baselineTranslationData = $this->getCurrentTranslationData($targetEnvironment, $translationField, $translationId, $localeField, $xliff->getPackage()->getSourceLocale());
        } else {
            $baselineTranslationData = (null === $localeField && $withBaseline ? $sourceData : []);
        }

        $xliffDoc = $xliff->getPackage()->addDocument(\sprintf('%s:%s:%s', $contentType->getName(), $source->getId(), (string) $sourceRevision->getId()));
        foreach ($fields as $fieldPath) {
            $propertyPath = Document::fieldPathToPropertyPath($fieldPath);
            foreach ($propertyAccessor->iterator($propertyPath, $sourceData, [XliffDocument::LOCALE_PLACE_HOLDER => $xliff->getPackage()->getSourceLocale()]) as $path => $value) {
                $sourcePath = \str_replace(XliffDocument::LOCALE_PLACE_HOLDER, $xliff->getPackage()->getSourceLocale(), $path);
                $targetPath = \str_replace(XliffDocument::LOCALE_PLACE_HOLDER, $targetLocale, $path);
                $currentValue = $propertyAccessor->getValue($currentData, $sourcePath);
                $translation = $propertyAccessor->getValue($currentTranslationData, $targetPath);
                $baseline = $propertyAccessor->getValue($baselineTranslationData, $targetPath);
                $isFinal = (null !== $targetEnvironment && $contentType->giveEnvironment()->getName() !== $targetEnvironment->getName() && $currentValue === $value && (null !== $translation || '' === $value));

                if (HtmlHelper::isHtml($value)) {
                    $xliffDoc->createHtml($path, $value, $translation, $baseline, $isFinal);
                } else {
                    $xliffDoc->createText($path, $value, $translation, $baseline, $isFinal);
                }
            }
        }
    }

    public function insert(Package $package, XliffDocument $document, ?string $localeField, ?string $translationField, ?Environment $publishAndArchive, ?string $username = null, bool $currentRevisionOnly = false): Revision
    {
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($this->getRevisionId($document));
        if ($currentRevisionOnly && !$revision->isCurrent()) {
            $this->logger->warning('log.service.xliff.not_current_revision', [
                'revision_id' => $this->getRevisionId($document),
                'ouuid' => $revision->giveOuuid(),
            ]);
            throw new XliffException($package, 'The source revision is not more the current revision of the document');
        }
        $targetLocale = $package->getTargetLocale();
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
        $document->unitToAssociativeArray($package, $data, $data);

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

    public function testInsert(Package $package, XliffDocument $document, ?string $localeField): void
    {
        [$contentType, $ouuid, $revisionId] = \explode(':', $document->id);
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        $revision = $this->revisionService->getByRevisionId($ouuid);
        $targetLocale = $package->getTargetLocale();

        $data = $revision->getRawData();
        if (null !== $localeField) {
            $propertyAccessor->setValue($data, Document::fieldPathToPropertyPath($localeField), $targetLocale);
        }
        $document->unitToAssociativeArray($package, $data, $data);
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

    private function getRevisionId(XliffDocument $document): string
    {
        return Type::string(\explode(':', $document->id)[2] ?? null);
    }
}

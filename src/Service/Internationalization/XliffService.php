<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Internationalization;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Psr\Log\LoggerInterface;

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
     * @param string[] $fields
     */
    public function extract(Document $source, Extractor $extractor, array $fields, ContentType $targetContentType, Environment $targetEnvironment, ?string $sourceDocumentField): void
    {
        $targetRevision = $this->getTargetRevision($source, $extractor, $fields, $targetContentType, $targetEnvironment, $sourceDocumentField);
        \dump($targetRevision);
//        $publishedRevision = $this->getPublishedRevisison($source, $sourceDocumentField, $targetEnvironment);
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
}

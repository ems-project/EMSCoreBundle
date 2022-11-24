<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

use Doctrine\ORM\EntityManagerInterface;
use Elastica\Document;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\Mapping;

final class RevisionSearcher
{
    private ElasticaService $elasticaService;
    private RevisionRepository $revisionRepository;
    private EntityManagerInterface $entityManager;
    private int $size;
    private string $timeout = self::DEFAULT_TIME_OUT;

    public const DEFAULT_TIME_OUT = '1m';

    public function __construct(
        ElasticaService $elasticaService,
        RevisionRepository $revisionRepository,
        EntityManagerInterface $entityManager,
        string $defaultScrollSize
    ) {
        $this->elasticaService = $elasticaService;
        $this->revisionRepository = $revisionRepository;
        $this->entityManager = $entityManager;
        $this->size = \intval($defaultScrollSize);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function setTimeout(string $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @param string[] $contentTypes
     */
    public function create(Environment $environment, string $query, array $contentTypes = [], bool $docs = false): RevisionSearch
    {
        $search = $this->elasticaService->convertElasticsearchBody(
            [$environment->getAlias()],
            $contentTypes,
            ['query' => Json::decode($query)]
        );
        $search->setSize($this->size);
        if (!$docs) {
            $search->setSources(['includes' => ['_id', Mapping::CONTENT_TYPE_FIELD]]);
        }

        $scroll = $this->elasticaService->scroll($search, $this->timeout);
        $total = $this->elasticaService->count($search);

        return new RevisionSearch($scroll, $total);
    }

    /**
     * @return iterable|Revisions[]
     */
    public function search(Environment $environment, RevisionSearch $search): iterable
    {
        $config = $this->entityManager->getConnection()->getConfiguration();
        $logger = $config->getSQLLogger();
        $config->setSQLLogger(null);

        foreach ($search->getScroll() as $resultSet) {
            $documents = $resultSet->getDocuments();
            /** @var string[] $ouuids */
            $ouuids = \array_map(fn (Document $doc) => $doc->getId(), $documents);
            $qb = $this->revisionRepository->searchByEnvironmentOuuids($environment, $ouuids);

            yield new Revisions($qb, $resultSet, $this->size);
        }

        $config->setSQLLogger($logger);
    }

    public function lock(Revisions $revisions, string $lockBy, string $until = '+5 minutes'): void
    {
        $untilDateTime = new \DateTime();
        $untilDateTime->modify($until);

        $this->revisionRepository->lockRevisionsById($revisions->getIds(), $lockBy, $untilDateTime);
    }

    public function unlock(Revisions $revisions): void
    {
        $this->revisionRepository->unlockRevisionsById($revisions->getIds());
    }
}

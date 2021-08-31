<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Search;

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
    private int $size;
    private string $timeout;

    public const DEFAULT_TIME_OUT = '1m';

    public function __construct(
        ElasticaService $elasticaService,
        RevisionRepository $revisionRepository,
        string $defaultScrollSize
    ) {
        $this->elasticaService = $elasticaService;
        $this->revisionRepository = $revisionRepository;
        $this->timeout = self::DEFAULT_TIME_OUT;
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
    public function create(Environment $environment, string $query, array $contentTypes = []): RevisionSearch
    {
        $search = $this->elasticaService->convertElasticsearchBody(
            [$environment->getAlias()],
            $contentTypes,
            ['query' => Json::decode($query)]
        );
        $search->setSources(['includes' => ['_id', Mapping::CONTENT_TYPE_FIELD]]);
        $search->setSize($this->size);

        $scroll = $this->elasticaService->scroll($search, $this->timeout);
        $total = $this->elasticaService->count($search);

        return new RevisionSearch($scroll, $total);
    }

    /**
     * @return iterable|Revisions[]
     */
    public function search(Environment $environment, RevisionSearch $search): iterable
    {
        foreach ($search->getScroll() as $resultSet) {
            $documents = $resultSet->getDocuments();
            /** @var string[] $ouuids */
            $ouuids = \array_map(fn (Document $doc) => $doc->getId(), $documents);

            $qb = $this->revisionRepository->searchByEnvironmentOuuids($environment, $ouuids);

            yield new Revisions($qb, $this->size);
        }
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

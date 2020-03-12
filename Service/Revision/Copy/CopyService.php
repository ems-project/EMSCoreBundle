<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Revision\Copy;

use EMS\CoreBundle\Service\ElasticsearchService;
use Psr\Log\LoggerInterface;

final class CopyService
{
    /** @var ElasticsearchService */
    private $elasticsearchService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ElasticsearchService $elasticsearchService,
        LoggerInterface $logger
    ) {
        $this->elasticsearchService = $elasticsearchService;
        $this->logger = $logger;
    }

    public function copy(CopyRequest $request)
    {
        foreach ($this->searchDocuments($request) as $hit) {
            //todo copy...
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function searchDocuments(CopyRequest $request): iterable
    {
        return $this->elasticsearchService->scroll($request->getEnvironment(), $request->getSearchQuery());
    }
}
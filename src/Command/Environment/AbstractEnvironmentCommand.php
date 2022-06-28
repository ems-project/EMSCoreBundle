<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractEnvironmentCommand extends AbstractCommand
{
    protected RevisionSearcher $revisionSearcher;
    protected EnvironmentService $environmentService;
    protected PublishService $publishService;

    protected string $searchQuery = '{}';

    public const OPTION_SCROLL_SIZE = 'scroll-size';
    public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    public const OPTION_SEARCH_QUERY = 'search-query';

    public function __construct(
        RevisionSearcher $revisionSearcher,
        EnvironmentService $environmentService,
        PublishService $publishService
    ) {
        parent::__construct();
        $this->revisionSearcher = $revisionSearcher;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;
    }

    protected function configureRevisionSearcher(): void
    {
        $this
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', '{}')
        ;
    }

    protected function initializeRevisionSearcher(): void
    {
        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
    }

    protected function choiceEnvironment(string $argument, string $question): Environment
    {
        $environmentNames = $this->environmentService->getEnvironmentNames();

        $this->choiceArgumentString($argument, $question, $environmentNames);

        return $this->environmentService->findByName($this->getArgumentString($argument));
    }
}

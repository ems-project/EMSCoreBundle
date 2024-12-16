<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractEnvironmentCommand extends AbstractCommand
{
    protected ?LoggerInterface $logger = null;

    protected string $searchQuery = '{}';
    protected string $lockUser = 'SYSTEM_ALIGN';
    protected bool $dryRun = false;

    final public const OPTION_SCROLL_SIZE = 'scroll-size';
    final public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    final public const OPTION_SEARCH_QUERY = 'search-query';
    final public const OPTION_USER = 'user';
    final public const OPTION_FORCE = 'force';
    final public const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        protected RevisionSearcher $revisionSearcher,
        protected EnvironmentService $environmentService,
        protected PublishService $publishService,
    ) {
        parent::__construct();
    }

    protected function configureForceProtection(): void
    {
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'If set, the task will be performed (protection)');
    }

    protected function configureRevisionSearcher(): void
    {
        $this
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', '{}')
            ->addOption(self::OPTION_USER, null, InputOption::VALUE_REQUIRED, 'Lock user', $this->lockUser)
            ->addOption(self::OPTION_DRY_RUN, '', InputOption::VALUE_NONE, 'Dry run')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->logger = new ConsoleLogger($output);
    }

    protected function initializeRevisionSearcher(string $lockUser): void
    {
        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
        $this->lockUser = $this->getOptionString(self::OPTION_USER, $lockUser);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
    }

    protected function choiceEnvironment(string $argument, string $question): Environment
    {
        $environmentNames = $this->environmentService->getEnvironmentNames();

        $this->choiceArgumentString($argument, $question, $environmentNames);

        return $this->environmentService->giveByName($this->getArgumentString($argument));
    }

    protected function forceProtection(InputInterface $input): bool
    {
        if (!$input->getOption(self::OPTION_FORCE)) {
            $this->io->error('Has protection, the force option is mandatory.');

            return false;
        }

        return true;
    }
}

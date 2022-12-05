<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CopyCommand extends AbstractCommand
{
    private Environment $environment;
    private string $searchQuery;
    /** @var ?array<mixed> */
    private ?array $mergeRawData = null;

    private const ARGUMENT_ENVIRONMENT = 'environment';
    private const ARGUMENT_SEARCH_QUERY = 'search-query';
    private const ARGUMENT_MERGE_RAW_DATA = 'merge-raw-data';
    public const OPTION_SCROLL_SIZE = 'scroll-size';
    public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';

    protected static $defaultName = Commands::REVISION_COPY;

    public function __construct(
        private readonly RevisionSearcher $revisionSearcher,
        private readonly EnvironmentService $environmentService,
        private readonly RevisionService $revisionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Copy revisions from search query')
            ->addArgument(self::ARGUMENT_ENVIRONMENT, InputArgument::REQUIRED, 'environment name')
            ->addArgument(self::ARGUMENT_SEARCH_QUERY, InputArgument::REQUIRED, 'search query')
            ->addArgument(self::ARGUMENT_MERGE_RAW_DATA, InputArgument::OPTIONAL, 'json merge raw data')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Timeout "scrollSize" items i.e. 30s or 2m')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Revision - Copy');

        $environmentName = $this->getArgumentString(self::ARGUMENT_ENVIRONMENT);
        $this->environment = $this->environmentService->giveByName($environmentName);
        $this->searchQuery = $this->getArgumentString(self::ARGUMENT_SEARCH_QUERY);

        $mergeString = $this->getArgumentStringNull(self::ARGUMENT_MERGE_RAW_DATA);
        $this->mergeRawData = $mergeString ? Json::decode($mergeString) : null;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $search = $this->revisionSearcher->create($this->environment, $this->searchQuery, [], true);

        $this->io->comment(\sprintf('Found %s hits', $search->getTotal()));
        $this->io->progressStart($search->getTotal());

        foreach ($this->revisionSearcher->search($this->environment, $search) as $revisions) {
            foreach ($revisions->transaction() as $revision) {
                $this->revisionService->copy($revision, $this->mergeRawData);
                $this->io->progressAdvance();
            }
        }

        $this->io->progressFinish();

        return self::EXECUTE_SUCCESS;
    }
}

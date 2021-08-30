<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AlignCommand extends AbstractCommand
{
    private RevisionService $revisionService;
    private LoggerInterface $logger;
    private DataService $data;
    private ContentTypeService $contentTypeService;
    private EnvironmentService $environmentService;
    private PublishService $publishService;
    private ElasticaService $elasticaService;

    private Environment $source;
    private Environment $target;

    private int $scrollSize;
    private string $scrollTimeout;
    private string $searchQuery;

    public const ARGUMENT_SOURCE = 'source';
    public const ARGUMENT_TARGET = 'target';
    public const ARGUMENT_SCROLL_SIZE = 'scrollSize';
    public const ARGUMENT_SCROLL_TIMEOUT = 'scrollTimeout';
    public const OPTION_FORCE = 'force';
    public const OPTION_SEARCH_QUERY = 'searchQuery';
    public const OPTION_SNAPSHOT = 'snapshot';
    public const DEFAULT_SCROLL_SIZE = 100;
    public const DEFAULT_SCROLL_TIMEOUT = '1m';
    public const DEFAULT_SEARCH_QUERY = '{}';

    protected static $defaultName = 'ems:environment:align';

    public function __construct(
        RevisionService $revisionService,
        LoggerInterface $logger,
        ElasticaService $elasticaService,
        DataService $data,
        ContentTypeService $contentTypeService,
        EnvironmentService $environmentService,
        PublishService $publishService
    ) {
        parent::__construct();
        $this->revisionService = $revisionService;
        $this->logger = $logger;
        $this->elasticaService = $elasticaService;
        $this->data = $data;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->publishService = $publishService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Align an environment from another one')
            ->addArgument(self::ARGUMENT_SOURCE, InputArgument::REQUIRED, 'Environment source name')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Environment target name')
            ->addArgument(self::ARGUMENT_SCROLL_SIZE, InputArgument::OPTIONAL, 'Size of the elasticsearch scroll request', self::DEFAULT_SCROLL_SIZE)
            ->addArgument(self::ARGUMENT_SCROLL_TIMEOUT, InputArgument::OPTIONAL, 'Time to migrate "scrollSize" items i.e. 30s or 2m', self::DEFAULT_SCROLL_TIMEOUT)
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', self::DEFAULT_SEARCH_QUERY)
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'If set, the task will be performed (protection)')
            ->addOption(self::OPTION_SNAPSHOT, null, InputOption::VALUE_NONE, 'If set, the target environment will be tagged as a snapshot after the alignment')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('EMS - Environment - Align');

        $this->scrollSize = $this->getArgumentInt(self::ARGUMENT_SCROLL_SIZE);
        $this->scrollTimeout = $this->getArgumentString(self::ARGUMENT_SCROLL_TIMEOUT);
        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->info('Interact with AlignCommand');

        $environmentNames = $this->environmentService->getEnvironmentNames();

        $this->choiceArgumentString(self::ARGUMENT_SOURCE, 'Select an existing environment as source', $environmentNames);
        $this->choiceArgumentString(self::ARGUMENT_TARGET, 'Select an existing environment as target', $environmentNames);

        $this->source = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_SOURCE));
        $this->target = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_TARGET));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Execute');

        if (!$input->getOption(self::OPTION_FORCE)) {
            $this->io->error('Has protection, the force option is mandatory.');

            return self::EXECUTE_ERROR;
        }

        $search = $this->revisionService->querySearchEnvironment($this->source, $this->searchQuery, $this->scrollSize);
        $scroll = $this->revisionService->scrollByEnvironment($this->source, $search, $this->scrollTimeout);
        $total = $this->revisionService->querySearchTotal($search);

        $this->io->note(\sprintf('The source environment contains %s elements, start aligning environments...', $total));
        $this->io->progressStart($total);

        $deletedRevision = 0;
        $alreadyAligned = 0;
        $targetIsPreviewEnvironment = [];

        foreach ($scroll as $revisions) {
            foreach ($revisions as $revision) {
                $contentType = $revision->giveContentType();

                if ($revision->getDeleted()) {
                    ++$deletedRevision;
                } elseif ($contentType->giveEnvironment()->getName() === $this->target->getName()) {
                    if (!isset($targetIsPreviewEnvironment[$contentType->getName()])) {
                        $targetIsPreviewEnvironment[$contentType->getName()] = 0;
                    }
                    ++$targetIsPreviewEnvironment[$contentType->getName()];
                } else {
                    if (0 == $this->publishService->publish($revision, $this->target, true)) {
                        ++$alreadyAligned;
                    }
                }

                $this->io->progressAdvance();
            }
        }

        $this->io->progressFinish();

        if ($input->getOption(self::OPTION_SNAPSHOT)) {
            $this->environmentService->setSnapshotTag($this->target);
            $this->io->note(\sprintf('The target environment "%s" was tagged as a snapshot', $this->target->getName()));
        }

        if ($deletedRevision > 0) {
            $this->io->caution(\sprintf('%s deleted revisions were not aligned', $deletedRevision));
        }

        if ($alreadyAligned > 0) {
            $this->io->note(\sprintf('%s revisions were already aligned', $alreadyAligned));
        }

        foreach ($targetIsPreviewEnvironment as $ctName => $counter) {
            $this->io->caution(\sprintf(
                '%s %s revisions were not aligned as %s is the default environment',
                $counter, $ctName, $this->target->getName()
            ));
        }

        $this->io->success(\vsprintf('Environments %s -> %s were aligned.', [
            $this->source->getName(),
            $this->target->getName(),
        ]));

        return self::EXECUTE_SUCCESS;
    }
}

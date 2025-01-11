<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISION_ARCHIVE,
    description: 'Archive documents for a given content type.',
    hidden: false
)]
final class ArchiveCommand extends AbstractCommand
{
    private ContentType $contentType;
    private string $searchQuery;
    private ?\DateTimeInterface $modifiedBefore = null;
    private const string USER = 'SYSTEM_ARCHIVE';

    public const string ARGUMENT_CONTENT_TYPE = 'content-type';
    public const string OPTION_FORCE = 'force';
    public const string OPTION_MODIFIED_BEFORE = 'modified-before';
    public const string OPTION_BATCH_SIZE = 'batch-size';
    public const string OPTION_SCROLL_SIZE = 'scroll-size';
    public const string OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    public const string OPTION_SEARCH_QUERY = 'search-query';

    public function __construct(
        private readonly RevisionSearcher $revisionSearcher,
        private readonly RevisionService $revisionService,
        private readonly ContentTypeService $contentTypeService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType name')
            ->addOption(self::OPTION_MODIFIED_BEFORE, null, InputOption::VALUE_REQUIRED, 'Y-m-dTH:i:s (2019-07-15T11:38:16)')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to import', '{}')
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Revision - Archive');

        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $contentTypeName = $this->getArgumentString(self::ARGUMENT_CONTENT_TYPE);
        $this->contentType = $this->contentTypeService->giveByName($contentTypeName);
        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);

        $modifiedBefore = $this->getOptionStringNull(self::OPTION_MODIFIED_BEFORE);
        if ($modifiedBefore) {
            $this->modifiedBefore = DateTime::create($modifiedBefore);
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = $this->contentType->giveEnvironment();
        $search = $this->revisionSearcher->create($environment, $this->searchQuery, [$this->contentType->getName()], true);

        $this->io->comment(\sprintf('Found %s hits', $search->getTotal()));
        $this->io->progressStart($search->getTotal());

        $counterNotModifiedBefore = $counterSuccess = 0;

        foreach ($this->revisionSearcher->search($environment, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, self::USER);

            foreach ($revisions->transaction() as $revision) {
                $revisionModified = $revision->getModified()->getTimestamp();
                if ($this->modifiedBefore && $revisionModified > $this->modifiedBefore->getTimestamp()) {
                    ++$counterNotModifiedBefore;
                    continue;
                }

                $this->revisionService->archive($revision, self::USER, true);
                ++$counterSuccess;

                $this->io->progressAdvance();
            }

            $this->revisionSearcher->unlock($revisions);
        }

        $this->io->progressFinish();

        if (null !== $this->modifiedBefore) {
            $this->io->comment(\sprintf('%d revisions not modified before', $counterNotModifiedBefore));
        }
        $this->io->success(\sprintf('%d revisions archived', $counterSuccess));

        return self::EXECUTE_SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Command\ContentType\ContentTypeLockCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RevisionArchiveCommand extends AbstractCommand
{
    private ContentTypeService $contentTypeService;
    private RevisionService $revisionService;

    private ContentType $contentType;
    /** @var array<mixed> */
    private array $search = [];
    private int $batchSize;

    protected static $defaultName = Commands::REVISION_ARCHIVE;
    private const USER = 'SYSTEM_ARCHIVE';

    public const ARGUMENT_CONTENT_TYPE = 'content-type';
    public const OPTION_FORCE = 'force';
    public const OPTION_MODIFIED_BEFORE = 'modified-before';
    public const OPTION_BATCH_SIZE = 'batch-size';

    public function __construct(
        ContentTypeService $contentTypeService,
        RevisionService $revisionService,
        int $defaultBulkSize
    ) {
        parent::__construct();
        $this->contentTypeService = $contentTypeService;
        $this->revisionService = $revisionService;
        $this->batchSize = $defaultBulkSize;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType name')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption(self::OPTION_MODIFIED_BEFORE, '', InputOption::VALUE_REQUIRED, 'Y-m-dTH:i:s (2019-07-15T11:38:16)')
            ->addOption(self::OPTION_BATCH_SIZE, '', InputOption::VALUE_REQUIRED, 'db records batch size', 'default_bulk_size')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Revision - Archive');

        $batchSize = \intval($input->getOption('batch-size'));
        if ($batchSize > 0) {
            $this->batchSize = $batchSize;
        }

        $contentTypeName = \strval($input->getArgument('content-type'));
        $this->contentType = $this->contentTypeService->giveByName($contentTypeName);

        $modifiedBefore = $input->getOption('modified-before');

        $this->search = \array_filter([
            'lockBy' => self::USER,
            'archived' => false,
            'contentType' => $this->contentType,
            'modifiedBefore' => $modifiedBefore ? DateTime::create(\strval($modifiedBefore)) : null,
        ], fn ($value) => null !== $value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progress = $this->io->createProgressBar();

        $lock = $this->lock($output, $this->contentType, \boolval($input->getOption('force')));
        if (ContentTypeLockCommand::RESULT_SUCCESS !== $lock) {
            return 0;
        }

        $countArchived = 0;
        $revisions = $this->revisionService->search($this->search);
        $revisions->setBatchSize($this->batchSize);

        $revisions->batch(function (Revision $revision) use ($progress, &$countArchived) {
            $this->revisionService->archive($revision, $revision->getLockBy(), false);
            $progress->advance();
            ++$countArchived;
        });

        $progress->finish();

        $this->io->success(\sprintf('Archived %d revisions', $countArchived));

        return 1;
    }

    private function lock(OutputInterface $output, ContentType $contentType, bool $force): int
    {
        try {
            if (null === $application = $this->getApplication()) {
                throw new \RuntimeException('could not find application');
            }

            return $application->find('ems:contenttype:lock')->run(
                new ArrayInput([
                    'contentType' => $contentType->getName(),
                    'time' => '+1day',
                    '--user' => self::USER,
                    '--force' => $force,
                ]),
                $output
            );
        } catch (\Throwable $e) {
            $this->io->error(\sprintf('Lock failed! (%s)', $e->getMessage()));

            return 0;
        }
    }
}

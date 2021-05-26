<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Command\LockCommand;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ArchiveCommand extends Command implements CommandInterface
{
    private SymfonyStyle $style;
    private ContentTypeService $contentTypeService;
    private RevisionService $revisionService;

    private ContentType $contentType;
    /** @var array<mixed> */
    private array $search = [];
    private int $batchSize;

    protected static $defaultName = 'ems:revision:archive';
    private const USER = 'SYSTEM_ARCHIVE';

    public function __construct(ContentTypeService $contentTypeService, RevisionService $revisionService, int $defaultBulkSize)
    {
        parent::__construct();
        $this->contentTypeService = $contentTypeService;
        $this->revisionService = $revisionService;
        $this->batchSize = $defaultBulkSize;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('content-type', InputArgument::REQUIRED, 'ContentType name')
            ->addOption('force', null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption('modified-before', '', InputOption::VALUE_REQUIRED, 'Y-m-dTH:i:s (2019-07-15T11:38:16)')
            ->addOption('batch-size', '', InputOption::VALUE_REQUIRED, 'db records batch size', '250')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->style = new SymfonyStyle($input, $output);
        $this->style->title('EMS - Revision - Archive');

        $this->batchSize = \intval($input->getOption('batchSize'));
        $contentTypeName = \strval($input->getArgument('contentType'));
        $this->contentType = $this->contentTypeService->giveByName($contentTypeName);

        $modifiedBefore = $input->getOption('modifiedBefore');

        $this->search = \array_filter([
            'lockBy' => self::USER,
            'archived' => false,
            'contentType' => $this->contentType,
            'modifiedBefore' => $modifiedBefore ? DateTime::create(\strval($modifiedBefore)) : null,
        ], fn ($value) => null !== $value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progress = $this->style->createProgressBar();

        $lock = $this->lock($output, $this->contentType, \boolval($input->getOption('force')));
        if (LockCommand::RESULT_SUCCESS !== $lock) {
            return 0;
        }

        $countArchived = 0;
        $revisions = $this->revisionService->search($this->search);

        $revisions->batch(function (Revision $revision) use ($progress, &$countArchived) {
            $this->revisionService->archive($revision, $revision->getLockBy(), false);
            $progress->advance();
            ++$countArchived;
        }, $this->batchSize);

        $progress->finish();

        $this->style->success(\sprintf('Archived %d revisions', $countArchived));

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
            $this->style->error(\sprintf('Lock failed! (%s)', $e->getMessage()));

            return 0;
        }
    }
}

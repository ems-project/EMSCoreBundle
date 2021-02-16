<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class LockCommand extends Command
{
    private ContentTypeRepository $contentTypeRepository;
    private RevisionRepository $revisionRepository;
    private SymfonyStyle $io;

    public function __construct(ContentTypeRepository $contentTypeRepository, RevisionRepository $revisionRepository)
    {
        parent::__construct();

        $this->contentTypeRepository = $contentTypeRepository;
        $this->revisionRepository = $revisionRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:contenttype:lock')
            ->setDescription('Lock a content type')
            ->addArgument('contentType', InputArgument::REQUIRED, 'content type to recompute')
            ->addArgument('time', InputArgument::REQUIRED, 'lock until (+1day, +5min, now)')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'lock username', 'EMS_COMMAND')
            ->addOption('force', null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption('if-empty', null, InputOption::VALUE_NONE, 'lock if there are no pending locks for the same user')
            ->addOption('ouuid', null, InputOption::VALUE_OPTIONAL, 'lock a specific ouuid', null)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Content-type lock command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeArgument = $input->getArgument('time');
        if (!\is_string($timeArgument)) {
            throw new \RuntimeException('Unexpected time argument');
        }

        $contentTypeName = $input->getArgument('contentType');
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $contentType = $this->contentTypeRepository->findOneBy(['name' => $contentTypeName]);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Content type not found');
        }
        if (($time = \strtotime($timeArgument)) === false) {
            throw new \RuntimeException('invalid time');
        }
        $by = $input->getOption('user');
        if (!\is_string($by)) {
            throw new \RuntimeException('Unexpected user name');
        }
        $force = true === $input->getOption('force');

        $until = new \DateTime();
        $until->setTimestamp($time);

        if ($input->getOption('if-empty') &&
            0 !== $this->revisionRepository->findAllLockedRevisions($contentType, $by)->count()) {
            return 0;
        }

        $ouuid = $input->getOption('ouuid') ? \strval($input->getOption('ouuid')) : null;
        $rows = $this->revisionRepository->lockRevisions($contentType, $until, $by, $force, $ouuid);

        if (0 === $rows) {
            $this->io->error('no revisions locked, try force?');
        } else {
            $this->io->success(\vsprintf('%s locked %d %s revisions until %s by %s', [
                ($force ? 'FORCE ' : ''),
                $rows,
                $contentType->getName(),
                $until->format('Y-m-d H:i:s'),
                $by,
            ]));
        }

        return 0;
    }
}

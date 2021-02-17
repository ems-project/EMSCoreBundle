<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Service\ElasticaService;
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
    private string $by;
    private ContentType $contentType;
    private ContentTypeRepository $contentTypeRepository;
    private ElasticaService $elasticaService;
    private bool $force;
    private SymfonyStyle $io;
    private RevisionRepository $revisionRepository;
    private \DateTime $until;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const ARGUMENT_TIME = 'time';
    private const OPTION_USER = 'user';
    private const OPTION_FORCE = 'force';
    private const OPTION_IF_EMPTY = 'if-empty';
    private const OPTION_OUUID = 'ouuid';

    public function __construct(ContentTypeRepository $contentTypeRepository, ElasticaService $elasticaService, RevisionRepository $revisionRepository)
    {
        parent::__construct();

        $this->contentTypeRepository = $contentTypeRepository;
        $this->elasticaService = $elasticaService;
        $this->revisionRepository = $revisionRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('ems:contenttype:lock')
            ->setDescription('Lock a content type')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'content type to recompute')
            ->addArgument(self::ARGUMENT_TIME, InputArgument::REQUIRED, 'lock until (+1day, +5min, now)')
            ->addOption(self::OPTION_USER, null, InputOption::VALUE_REQUIRED, 'lock username', 'EMS_COMMAND')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption(self::OPTION_IF_EMPTY, null, InputOption::VALUE_NONE, 'lock if there are no pending locks for the same user')
            ->addOption(self::OPTION_OUUID, null, InputOption::VALUE_OPTIONAL, 'lock a specific ouuid', null)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Content-type lock command');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $timeArgument = $input->getArgument(self::ARGUMENT_TIME);
        if (!\is_string($timeArgument)) {
            throw new \RuntimeException('Unexpected time argument');
        }
        if (($time = \strtotime($timeArgument)) === false) {
            throw new \RuntimeException('invalid time');
        }
        $until = new \DateTime();
        $until->setTimestamp($time);
        $this->until = $until;

        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $contentType = $this->contentTypeRepository->findOneBy(['name' => $contentTypeName]);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Content type not found');
        }
        $this->contentType = $contentType;

        $by = $input->getOption(self::OPTION_USER);
        if (!\is_string($by)) {
            throw new \RuntimeException('Unexpected user name');
        }
        $this->by = $by;

        $this->force = true === $input->getOption(self::OPTION_FORCE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_IF_EMPTY) &&
            0 !== $this->revisionRepository->findAllLockedRevisions($this->contentType, $this->by)->count()) {
            return 0;
        }

        $ouuid = $input->getOption(self::OPTION_OUUID) ? \strval($input->getOption(self::OPTION_OUUID)) : null;
        $rows = $this->revisionRepository->lockRevisions($this->contentType, $this->until, $this->by, $this->force, $ouuid);

        if (0 === $rows) {
            $this->io->error('no revisions locked, try force?');
        } else {
            $this->io->success(\vsprintf('%s locked %d %s revisions until %s by %s', [
                ($this->force ? 'FORCE ' : ''),
                $rows,
                $this->contentType->getName(),
                $this->until->format('Y-m-d H:i:s'),
                $this->by,
            ]));
        }

        return 0;
    }
}

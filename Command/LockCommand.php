<?php

namespace EMS\CoreBundle\Command;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LockCommand extends Command
{
    /**
     * @var ContentTypeRepository
     */
    private $contentTypeRepository;

    /**
     * @var RevisionRepository
     */
    private $revisionRepository;

    public function __construct(Registry $doctrine)
    {
        parent::__construct();

        $em = $doctrine->getManager();
        $this->contentTypeRepository = $em->getRepository(ContentType::class);
        $this->revisionRepository = $em->getRepository(Revision::class);
    }

    protected function configure()
    {
        $this
            ->setName('ems:contenttype:lock')
            ->setDescription('Lock a content type')
            ->addArgument('contentType', InputArgument::REQUIRED, 'content type to recompute')
            ->addArgument('time', InputArgument::REQUIRED, 'lock until (+1day, +5min, now)')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'lock username', 'EMS_COMMAND')
            ->addOption('force', null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption('if-empty', null, InputOption::VALUE_NONE, 'lock if there are no pending locks for the same user')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'lock a specific id')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var $contentType ContentType */
        if (null === $contentType = $this->contentTypeRepository->findOneBy(['name' => $input->getArgument('contentType')])) {
            throw new RuntimeException('invalid content type');
        }
        if (!$time = strtotime($input->getArgument('time'))) {
            throw new RuntimeException('invalid time');
        }
        $by = $input->getOption('user');
        $force = $input->getOption('force');

        $until = new DateTime();
        $until->setTimestamp($time);

        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('if-empty') &&
            0 !== $this->revisionRepository->findAllLockedRevisions($contentType, $by)->count()) {
            return;
        }

        $rows = $this->revisionRepository->lockRevisions($contentType, $until, $by, $force, $input->getOption('id'));

        if (0 === $rows) {
            $io->error('no revisions locked, try force?');
        } else {
            $io->success(vsprintf('%s locked %d %s revisions until %s by %s', [
                ($force ? 'FORCE ' : ''),
                $rows,
                $contentType->getName(),
                $until->format('Y-m-d H:i:s'),
                $by
            ]));
        }
    }
}

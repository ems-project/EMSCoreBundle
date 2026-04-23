<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Command\AbstractCoreCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: Commands::REVISIONS_TIME_MACHINE, description: 'Revert to a copy of the revision wich was the current one for a given timestamp.', aliases: ['ems:revision:time-machine'], hidden: false)]
final class TimeMachineCommand extends AbstractCoreCommand
{
    private readonly ObjectManager $em;

    private const string DEFAULT_USERNAME = 'SYSTEM_TIME_MACHINE';
    public const int RESULT_NOT_FOUND = 1;
    public const int RESULT_EQUALS_IN_TIME = 2;
    public const int RESULT_SUCCESS = 3;

    public const array RESULTS = [
        self::RESULT_NOT_FOUND => 'Not found in time revision',
        self::RESULT_EQUALS_IN_TIME => 'Revision in time property equals current revision property',
        self::RESULT_SUCCESS => 'New revision with in time revision property data',
    ];

    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly DataService $dataService,
        Registry $doctrine,
        private readonly IndexService $indexService,
    ) {
        parent::__construct();
        $this->em = $doctrine->getManager();
    }

    #[\Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('emsLink', InputArgument::REQUIRED, 'ems link ems://object:company:ouuid')
            ->addArgument('datetime', InputArgument::REQUIRED, 'Y-m-dTH:i:s (2019-07-15T11:38:16)')
            ->addArgument('propertyPath', InputArgument::REQUIRED, 'property to compare')
        ;
        $this->addUsernameOption(self::DEFAULT_USERNAME);
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Revision - TimeMachine');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $propertyPath = (string) $input->getArgument('propertyPath');
        $path = \explode('.', $propertyPath);
        $emsLink = EMSLink::fromText((string) $input->getArgument('emsLink'));
        $dateTime = DateTime::create((string) $input->getArgument('datetime'));

        $this->io->note(\sprintf('Searching for history revision on %s', $dateTime->format('d/m/Y H:i:s')));

        $currentRevision = $this->revisionService->getByEmsLink($emsLink);
        if (null === $currentRevision) {
            $this->io->note(\sprintf('Revision not found for %s', $emsLink));

            return self::RESULT_NOT_FOUND;
        }

        $historyRevision = $this->revisionService->getByEmsLink($emsLink, $dateTime);
        if (null === $historyRevision) {
            $this->io->note(\sprintf('Could not find revision on %s', $dateTime->format(\DATE_ATOM)));

            return self::RESULT_NOT_FOUND;
        }

        $currentRawData = $currentRevision->getRawData();
        $historyRawData = $historyRevision->getRawData();

        $inTimeRaw = $this->goBackInTime($currentRawData, $historyRawData, $path);
        $inTimeDate = $historyRevision->getStartTime()->format(\DATE_ATOM);

        if ($inTimeRaw === $currentRevision->getRawData()) {
            $this->io->note(\sprintf('Revision in time on %s for property "%s" equals the current', $inTimeDate, $propertyPath));

            return self::RESULT_EQUALS_IN_TIME;
        }

        $this->io->success(\sprintf('Revision %d has been updated with in time "%s" property', $currentRevision->getId(), $propertyPath));

        $this->dataService->lockRevision($currentRevision, null, false, $this->getUsername());

        $revertedRevision = $currentRevision->convertToDraft($this->getUsername());
        $revertedRevision->setRawData($inTimeRaw);
        $revertedRevision->setDraft(false);
        $revertedRevision->setFinalizedBy($this->getUsername());

        $this->dataService->sign($revertedRevision);

        $currentRevision->close(new \DateTime('now'), $this->getUsername());

        $this->em->persist($currentRevision);
        $this->em->persist($revertedRevision);
        $this->em->flush();

        $this->indexService->indexRevision($revertedRevision);
        $this->dataService->unlockRevision($currentRevision, $this->getUsername());

        return self::RESULT_SUCCESS;
    }

    /**
     * @param array<mixed> $currentRaw
     * @param array<mixed> $historyRaw
     * @param string[]     $path
     *
     * @return array<mixed>
     */
    private function goBackInTime(array $currentRaw, array $historyRaw, array $path): array
    {
        $property = \array_shift($path);

        if (!\is_string($property)) {
            $this->io->error('Property path is empty');

            return $currentRaw;
        }

        $currentProperty = $currentRaw[$property] ?? null;
        $historyProperty = $historyRaw[$property] ?? null;

        if (null === $historyProperty) {
            $this->io->warning(\sprintf('Could not find data in time for property %s', $property));

            return $currentRaw;
        }

        if (\is_array($currentProperty) && [] !== $path) {
            if (\count($currentProperty) !== (\is_countable($historyProperty) ? \count($historyProperty) : 0)) {
                $this->io->warning('Could not go back in time for different sized collections!');

                return $currentRaw;
            }

            foreach ($currentProperty as $i => $currentItem) {
                $currentRaw[$property][$i] = $this->goBackInTime($currentItem, $historyProperty[$i], $path);
            }
        }

        if ([] === $path) {
            $currentRaw[$property] = $historyProperty;
        }

        return $currentRaw;
    }
}

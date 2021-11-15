<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Helper\Xliff\Inserter;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCommand extends AbstractCommand
{
    protected static $defaultName = Commands::XLIFF_UPDATE;
    public const ARGUMENT_XLIFF_FILE = 'xliff-file';
    public const OPTION_PUBLISH_ARCHIVE = 'publish-archive';

    private EnvironmentService $environmentService;
    private XliffService $xliffService;

    private string $xliffFilename;
    private ?Environment $publishAndArchive = null;

    public function __construct(
        EnvironmentService $environmentService,
        XliffService $xliffService
    ) {
        $this->environmentService = $environmentService;
        $this->xliffService = $xliffService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_XLIFF_FILE, InputArgument::REQUIRED, 'Input XLIFF file')
            ->addOption(self::OPTION_PUBLISH_ARCHIVE, null, InputOption::VALUE_OPTIONAL, 'If defined the revision will be published in the defined environment than the document will be archived in it\'s default environment');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Update');

        $this->xliffFilename = $this->getArgumentString(self::ARGUMENT_XLIFF_FILE);
        $this->publishAndArchive = $this->environmentService->giveByName($this->getOptionString(self::OPTION_PUBLISH_ARCHIVE));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF update from file %s', $this->xliffFilename),
        ]);

        $translatedXliff = new \SimpleXMLElement($this->xliffFilename, 0, true);
        $inserter = new Inserter($translatedXliff);
        $this->io->progressStart($inserter->count());
        foreach ($inserter->getDocuments() as $document) {
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $output->writeln('');

        return self::EXECUTE_SUCCESS;
    }
}

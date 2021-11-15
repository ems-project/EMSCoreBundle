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
    public const OPTION_TRANSLATION_FIELD = 'translation-field';
    public const OPTION_LOCALE_FIELD = 'locale-field';

    private EnvironmentService $environmentService;
    private XliffService $xliffService;

    private string $xliffFilename;
    private ?Environment $publishAndArchive = null;
    private string $translationField;
    private string $localeField;

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
            ->addOption(self::OPTION_PUBLISH_ARCHIVE, null, InputOption::VALUE_OPTIONAL, 'If defined the revision will be published in the defined environment than the document will be archived in it\'s default environment')
            ->addOption(self::OPTION_LOCALE_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the locale', 'locale')
            ->addOption(self::OPTION_TRANSLATION_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the translation field', 'translation_id');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Update');

        $this->xliffFilename = $this->getArgumentString(self::ARGUMENT_XLIFF_FILE);
        $this->publishAndArchive = $this->environmentService->giveByName($this->getOptionString(self::OPTION_PUBLISH_ARCHIVE));
        $this->translationField = $this->getOptionString(self::OPTION_TRANSLATION_FIELD);
        $this->localeField = $this->getOptionString(self::OPTION_LOCALE_FIELD);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF update from file %s', $this->xliffFilename),
        ]);

        $inserter = Inserter::fromFile($this->xliffFilename);
        $this->io->progressStart($inserter->count());
        foreach ($inserter->getDocuments() as $document) {
            $this->xliffService->insert($document, $this->localeField, $this->translationField, $this->publishAndArchive);
            //TODO: publish and archive if needed
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $output->writeln('');

        return self::EXECUTE_SUCCESS;
    }
}

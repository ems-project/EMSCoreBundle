<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Xliff\Xliff\Entity\InsertReport;
use EMS\Xliff\Xliff\Inserter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCommand extends AbstractCommand
{
    private const XLIFF_UPLOAD_COMMAND = 'XLIFF_UPLOAD_COMMAND';
    protected static $defaultName = Commands::XLIFF_UPDATE;
    public const ARGUMENT_XLIFF_FILE = 'xliff-file';
    public const OPTION_PUBLISH_TO = 'publish-to';
    public const OPTION_ARCHIVE = 'archive';
    public const OPTION_TRANSLATION_FIELD = 'translation-field';
    public const OPTION_LOCALE_FIELD = 'locale-field';
    public const OPTION_DRY_RUN = 'dry-run';

    private EnvironmentService $environmentService;
    private XliffService $xliffService;
    private PublishService $publishService;
    private RevisionService $revisionService;

    private string $xliffFilename;
    private ?Environment $publishTo = null;
    private bool $archive = false;
    private string $translationField;
    private string $localeField;
    private bool $dryRun = false;

    public function __construct(
        EnvironmentService $environmentService,
        XliffService $xliffService,
        PublishService $publishService,
        RevisionService $revisionService
    ) {
        $this->environmentService = $environmentService;
        $this->xliffService = $xliffService;
        $this->publishService = $publishService;
        $this->revisionService = $revisionService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_XLIFF_FILE, InputArgument::REQUIRED, 'Input XLIFF file')
            ->addOption(self::OPTION_PUBLISH_TO, null, InputOption::VALUE_OPTIONAL, 'If defined the revision will be published in the defined environment')
            ->addOption(self::OPTION_ARCHIVE, null, InputOption::VALUE_NONE, 'If set another revision will be flagged as archived')
            ->addOption(self::OPTION_LOCALE_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the locale', 'locale')
            ->addOption(self::OPTION_TRANSLATION_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the translation field', 'translation_id')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'If set nothing is saved in the database');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Update');

        $this->xliffFilename = $this->getArgumentString(self::ARGUMENT_XLIFF_FILE);
        $environmentName = $this->getOptionStringNull(self::OPTION_PUBLISH_TO);
        $this->publishTo = null === $environmentName ? null : $this->environmentService->giveByName($environmentName);
        $this->archive = $this->getOptionBool(self::OPTION_ARCHIVE);
        $this->translationField = $this->getOptionString(self::OPTION_TRANSLATION_FIELD);
        $this->localeField = $this->getOptionString(self::OPTION_LOCALE_FIELD);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);

        if ($this->archive && null === $this->publishTo) {
            throw new \RuntimeException(\sprintf('The %s option can be activate only if the %s option is DEFINED', self::OPTION_ARCHIVE, self::OPTION_PUBLISH_TO));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF update from file %s', $this->xliffFilename),
        ]);

        $inserter = Inserter::fromFile($this->xliffFilename);
        $this->io->progressStart($inserter->count());
        $insertReport = new InsertReport();
        foreach ($inserter->getDocuments() as $document) {
            if ($this->dryRun) {
                $this->xliffService->testInsert($insertReport, $document, $this->localeField);
                $this->io->progressAdvance();
                continue;
            }
            $revision = $this->xliffService->insert($insertReport, $document, $this->localeField, $this->translationField, $this->publishTo, self::XLIFF_UPLOAD_COMMAND);
            if (null !== $this->publishTo) {
                $this->publishService->publish($revision, $this->publishTo, self::XLIFF_UPLOAD_COMMAND);
            }
            if ($this->archive) {
                $this->revisionService->archive($revision, self::XLIFF_UPLOAD_COMMAND);
            }
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        if ($insertReport->countErrors() > 0) {
            $output->writeln(\sprintf('%d documents faced an issue', $insertReport->countErrors()));
            $filename = \tempnam(\sys_get_temp_dir(), 'xliff_update_report_').'.zip';
            $insertReport->export($filename);
            $output->writeln(\sprintf('See %s for details', $filename));
        }

        return self::EXECUTE_SUCCESS;
    }
}

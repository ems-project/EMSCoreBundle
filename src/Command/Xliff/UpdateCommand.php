<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\XliffException;
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
    public const OPTION_CURRENT_REVISION_ONLY = 'current-revision-only';

    private string $xliffFilename;
    private ?Environment $publishTo = null;
    private bool $archive = false;
    private ?string $translationField = null;
    private ?string $localeField = null;
    private bool $dryRun = false;
    private bool $currentRevisionOnly = false;

    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly XliffService $xliffService,
        private readonly PublishService $publishService,
        private readonly RevisionService $revisionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_XLIFF_FILE, InputArgument::REQUIRED, 'Input XLIFF file')
            ->addOption(self::OPTION_PUBLISH_TO, null, InputOption::VALUE_OPTIONAL, 'If defined the revision will be published in the defined environment')
            ->addOption(self::OPTION_ARCHIVE, null, InputOption::VALUE_NONE, 'If set another revision will be flagged as archived')
            ->addOption(self::OPTION_LOCALE_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the locale', null)
            ->addOption(self::OPTION_TRANSLATION_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the translation field', null)
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'If set nothing is saved in the database')
            ->addOption(self::OPTION_CURRENT_REVISION_ONLY, null, InputOption::VALUE_NONE, 'Translations will be updated only is the source revision is still a current revision');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - XLIFF - Update');

        $this->xliffFilename = $this->getArgumentString(self::ARGUMENT_XLIFF_FILE);
        $environmentName = $this->getOptionStringNull(self::OPTION_PUBLISH_TO);
        $this->publishTo = null === $environmentName ? null : $this->environmentService->giveByName($environmentName);
        $this->archive = $this->getOptionBool(self::OPTION_ARCHIVE);
        $this->translationField = $this->getOptionStringNull(self::OPTION_TRANSLATION_FIELD);
        $this->localeField = $this->getOptionStringNull(self::OPTION_LOCALE_FIELD);
        $this->dryRun = $this->getOptionBool(self::OPTION_DRY_RUN);
        $this->currentRevisionOnly = $this->getOptionBool(self::OPTION_CURRENT_REVISION_ONLY);

        if ($this->archive && null === $this->publishTo) {
            throw new \RuntimeException(\sprintf('The %s option can be activate only if the %s option is DEFINED', self::OPTION_ARCHIVE, self::OPTION_PUBLISH_TO));
        }

        if (null === $this->translationField && $this->translationField !== $this->localeField) {
            throw new \RuntimeException(\sprintf('Both %s and %s options must be defined or not defined at all (fields defined with %%locale%% placeholder)', self::OPTION_TRANSLATION_FIELD, self::OPTION_LOCALE_FIELD));
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
            try {
                $revision = $this->xliffService->insert($insertReport, $document, $this->localeField, $this->translationField, $this->publishTo, self::XLIFF_UPLOAD_COMMAND, $this->currentRevisionOnly);
            } catch (XliffException $e) {
                $output->writeln(\sprintf('Update for %s:%s:%s failed :  %s', $document->getContentType(), $document->getOuuid(), $document->getRevisionId(), $e->getMessage()));
                continue;
            }
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

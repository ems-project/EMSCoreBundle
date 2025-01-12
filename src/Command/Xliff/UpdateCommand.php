<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Xliff;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CommonBundle\Twig\AssetRuntime;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\XliffException;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Internationalization\XliffService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\File\TempFile;
use EMS\Helpers\Html\MimeTypes;
use EMS\Xliff\Xliff\Entity\InsertReport;
use EMS\Xliff\Xliff\Inserter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: Commands::XLIFF_UPDATE,
    description: 'Update documents from a given XLIFF file.',
    hidden: false
)]
final class UpdateCommand extends AbstractCommand
{
    private const string XLIFF_UPLOAD_COMMAND = 'XLIFF_UPLOAD_COMMAND';
    public const string ARGUMENT_XLIFF_FILE = 'xliff-file';
    public const string OPTION_PUBLISH_TO = 'publish-to';
    public const string OPTION_ARCHIVE = 'archive';
    public const string OPTION_TRANSLATION_FIELD = 'translation-field';
    public const string OPTION_LOCALE_FIELD = 'locale-field';
    public const string OPTION_DRY_RUN = 'dry-run';
    public const string OPTION_CURRENT_REVISION_ONLY = 'current-revision-only';
    public const string OPTION_BASE_URL = 'base-url';

    private string $xliffFilename;
    private ?Environment $publishTo = null;
    private bool $archive = false;
    private ?string $translationField = null;
    private ?string $localeField = null;
    private bool $dryRun = false;
    private bool $currentRevisionOnly = false;
    private ?string $baseUrl = null;

    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly XliffService $xliffService,
        private readonly PublishService $publishService,
        private readonly RevisionService $revisionService,
        private readonly StorageManager $storageManager,
        private readonly AssetRuntime $assetRuntime,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_XLIFF_FILE, InputArgument::REQUIRED, 'Input XLIFF file (filename or hash)')
            ->addOption(self::OPTION_PUBLISH_TO, null, InputOption::VALUE_OPTIONAL, 'If defined the revision will be published in the defined environment')
            ->addOption(self::OPTION_ARCHIVE, null, InputOption::VALUE_NONE, 'If set another revision will be flagged as archived')
            ->addOption(self::OPTION_LOCALE_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the locale', null)
            ->addOption(self::OPTION_TRANSLATION_FIELD, null, InputOption::VALUE_OPTIONAL, 'Field containing the translation field', null)
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'If set nothing is saved in the database')
            ->addOption(self::OPTION_CURRENT_REVISION_ONLY, null, InputOption::VALUE_NONE, 'Translations will be updated only is the source revision is still a current revision')
            ->addOption(self::OPTION_BASE_URL, null, InputOption::VALUE_OPTIONAL, 'Base url, in order to generate a download link to the error report');
    }

    #[\Override]
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
        $this->baseUrl = $this->getOptionStringNull(self::OPTION_BASE_URL);

        if ($this->archive && null === $this->publishTo) {
            throw new \RuntimeException(\sprintf('The %s option can be activate only if the %s option is DEFINED', self::OPTION_ARCHIVE, self::OPTION_PUBLISH_TO));
        }

        if (null === $this->translationField && $this->translationField !== $this->localeField) {
            throw new \RuntimeException(\sprintf('Both %s and %s options must be defined or not defined at all (fields defined with %%locale%% placeholder)', self::OPTION_TRANSLATION_FIELD, self::OPTION_LOCALE_FIELD));
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->text([
            \sprintf('Starting the XLIFF update from file %s', $this->xliffFilename),
        ]);

        $fileGetter = $this->storageManager->getFile($this->xliffFilename);
        $inserter = Inserter::fromFile($fileGetter->getFilename());
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

        if (0 === $insertReport->countErrors()) {
            return self::EXECUTE_SUCCESS;
        }

        $output->writeln(\sprintf('%d documents faced issue(s)', $insertReport->countErrors()));
        $tempFile = TempFile::create();
        $insertReport->export($tempFile->path);
        $hash = $this->storageManager->saveFile($tempFile->path, StorageInterface::STORAGE_USAGE_CONFIG);

        $url = ($this->baseUrl ?? '').$this->assetRuntime->assetPath(
            [
                EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
                EmsFields::CONTENT_FILE_NAME_FIELD => 'xliff_update_report.zip',
                EmsFields::CONTENT_MIME_TYPE_FIELD => MimeTypes::APPLICATION_ZIP->value,
            ],
            [],
            'ems_asset',
            EmsFields::CONTENT_FILE_HASH_FIELD,
            EmsFields::CONTENT_FILE_NAME_FIELD,
            EmsFields::CONTENT_MIME_TYPE_FIELD,
            UrlGeneratorInterface::ABSOLUTE_PATH
        );
        $output->writeln('');
        $output->writeln(\sprintf('The XLIFF export is available at %s', $url));

        return self::EXECUTE_SUCCESS;
    }
}

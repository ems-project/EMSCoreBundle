<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\PropertyAccess\PropertyAccessor;
use EMS\CommonBundle\Contracts\Spreadsheet\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Service\ExpressionService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\Helpers\File\File;
use EMS\Helpers\File\TempFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::SUBMISSION_EXPORT,
    description: 'Extract form submissions',
    hidden: false
)]
class ExportCommand extends AbstractCommand
{
    public const MAIL_TEMPLATE = '@EMSCore/email/submissions-export.html.twig';
    public const ARG_FIELDS = 'fields';
    public const OPTION_FILTER = 'filter';
    public const OPTION_FILENAME = 'filename';
    public const OPTION_EMAIL_TO = 'email-to';
    public const OPTION_EMAIL_SUBJECT = 'email-subject';
    public const OPTION_EXPORT_FORMAT = 'format';

    /** @var string[] */
    private array $fields;
    private ?string $filter = null;
    private ?string $filename = null;
    /**
     * @var string[]
     */
    private array $emailsTo;
    private string $subject;
    private ?string $format = null;

    public function __construct(
        private readonly FormSubmissionService $formSubmissionService,
        private readonly ExpressionService $expressionService,
        private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService,
        private readonly MailerService $mailerService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARG_FIELDS,
                InputArgument::IS_ARRAY,
                'Fields to export in a property accessor format [instance] [name] [locale] [submission_date] [data][email] [data][multi-choice][level_0] [data][multi-choice][level_1]'
            )->addOption(
                self::OPTION_FILTER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Expression to filter submissions, e.g. "\'true\' == (data[\'recontact-optin\'] ?? \'false\')". The following variables are available: data (array), instance (string), name (string), locale (string), submission_date (date in the ISO 8601 format)'
            )->addOption(
                self::OPTION_FILENAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'Export filename, xlsx or csv formats are supported',
            )->addOption(
                self::OPTION_EMAIL_TO,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Email addresses where the export will be sent',
            )->addOption(
                self::OPTION_EMAIL_SUBJECT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Email\'s subject',
                'Submissions export'
            )->addOption(
                self::OPTION_EXPORT_FORMAT,
                null,
                InputOption::VALUE_OPTIONAL,
                \sprintf('Format of the export. Supported formats: %s', \implode(', ', SpreadsheetGeneratorServiceInterface::FORMAT_WRITERS)),
            );
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->fields = $this->getArgumentStringArray(self::ARG_FIELDS);
        $this->filter = $this->getOptionStringNull(self::OPTION_FILTER);
        $this->filename = $this->getOptionStringNull(self::OPTION_FILENAME);
        $this->emailsTo = $this->getOptionStringArray(self::OPTION_EMAIL_TO, false);
        $this->subject = $this->getOptionString(self::OPTION_EMAIL_SUBJECT);
        $this->format = $this->getOptionStringNull(self::OPTION_EXPORT_FORMAT);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Export the form submissions');
        $sheet = [];

        $this->io->progressStart($this->formSubmissionService->count());
        $propertyAccessor = PropertyAccessor::createPropertyAccessor();
        foreach ($this->formSubmissionService->getUnprocessed() as $submission) {
            $data = [
                'instance' => $submission->getInstance(),
                'name' => $submission->getName(),
                'locale' => $submission->getLocale(),
                'submission_date' => $submission->getCreated()->format('c'),
                'data' => $submission->getData() ?? [],
            ];
            if (null !== $this->filter && !$this->expressionService->evaluateToBool($this->filter, $data)) {
                $this->io->progressAdvance();
                continue;
            }
            $line = [];
            foreach ($this->fields as $field) {
                $line[] = $propertyAccessor->getValue($data, $field) ?? '';
            }
            $sheet[] = $line;
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        if (null === $this->filename && empty($this->emailsTo)) {
            $this->io->table([...$this->fields], $sheet);

            return self::EXECUTE_SUCCESS;
        }
        $extension = $this->getFormat();

        $config = [
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'rows' => [[...$this->fields], ...$sheet],
                'name' => 'submissions',
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'submissions',
            SpreadsheetGeneratorServiceInterface::WRITER => $extension,
        ];
        $tempFile = TempFile::create();
        $this->spreadsheetGeneratorService->generateSpreadsheetFile($config, $tempFile->path);

        $this->generateFile($tempFile);
        $this->sendEmail($tempFile);

        return self::EXECUTE_SUCCESS;
    }

    private function generateFile(TempFile $tempFile): void
    {
        if (null == $this->filename) {
            return;
        }
        File::putContents($this->filename, $tempFile->getContents());
        $this->io->success(\sprintf('The file %s has been successfully generated', $this->filename));
    }

    private function sendEmail(TempFile $tempFile): void
    {
        if (empty($this->emailsTo)) {
            return;
        }
        $mailTemplate = $this->mailerService->makeMailTemplate(self::MAIL_TEMPLATE);
        foreach ($this->emailsTo as $email) {
            $mailTemplate->addTo($email);
        }
        $mailTemplate->setSubject($this->subject);
        $mailTemplate->setBodyBlock('body');
        $mailTemplate->addAttachment($tempFile->path, \sprintf('crm-export.%s', $this->format));
        $this->mailerService->sendMailTemplate($mailTemplate);
    }

    private function getFormat(): string
    {
        $fileExtension = null;
        if (null !== $this->filename) {
            $fileExtension = \pathinfo($this->filename)['extension'] ?? null;
            if (!\in_array($fileExtension, SpreadsheetGeneratorServiceInterface::FORMAT_WRITERS)) {
                $this->io->error(\sprintf('File extension %s is not supported', $fileExtension));
            }
        }
        $this->format ??= $fileExtension ?? SpreadsheetGeneratorServiceInterface::XLSX_WRITER;

        if (null !== $fileExtension && $fileExtension !== $this->format) {
            $this->io->error(\sprintf('Export format %s mismatched with the file extension %s', $this->format, $this->filename));
        }

        return $this->format;
    }
}

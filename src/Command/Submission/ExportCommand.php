<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Service\ExpressionService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends AbstractCommand
{
    protected static $defaultName = Commands::SUBMISSION_EXPORT;
    public const ARG_FIELDS = 'fields';
    public const OPTION_FILTER = 'filter';
    public const OPTION_FILENAME = 'filename';

    /** @var string[] */
    private array $fields;
    private ?string $filter;
    private ?string  $filename;

    public function __construct(
        private readonly FormSubmissionService $formSubmissionService,
        private readonly ExpressionService $expressionService,
        private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Extract form submissions')
            ->addArgument(
                self::ARG_FIELDS,
                InputArgument::IS_ARRAY,
                'Fields to export'
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
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->fields = $this->getArgumentStringArray(self::ARG_FIELDS);
        $this->filter = $this->getOptionStringNull(self::OPTION_FILTER);
        $this->filename = $this->getOptionStringNull(self::OPTION_FILENAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->section('Export the form submissions');
        $sheet = [];

        $this->io->progressStart($this->formSubmissionService->count());
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
                $line[] = $data['data'][$field] ?? $data[$field] ?? '';
            }
            $sheet[] = $line;
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        if (null === $this->filename) {
            $this->io->table([...$this->fields], $sheet);

            return self::EXECUTE_SUCCESS;
        }

        $extension = \pathinfo($this->filename)['extension'] ?? '';
        if (!\in_array($extension, SpreadsheetGeneratorServiceInterface::FORMAT_WRITERS)) {
            $this->io->error(\sprintf('File format %s is not supported', $extension));
        }

        $config = [
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'rows' => [[...$this->fields], ...$sheet],
                'name' => 'submissions',
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'submissions',
            SpreadsheetGeneratorServiceInterface::WRITER => $extension,
        ];
        $this->spreadsheetGeneratorService->generateSpreadsheetFile($config, $this->filename);
        $this->io->success(\sprintf('The file %s has been successfully generated', $this->filename));

        return self::EXECUTE_SUCCESS;
    }
}

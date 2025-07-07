<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Submission;

use EMS\CommonBundle\Contracts\Spreadsheet\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Service\ExpressionService;
use EMS\CoreBundle\Command\Submission\ExportCommand;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use EMS\Helpers\File\TempFile;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Twig\Environment;

final readonly class SubmissionExporter
{
    public function __construct(
        private FormSubmissionService $formSubmissionService,
        private ExpressionService $expressionService,
        private SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService,
        private MailerService $mailerService,
        private Environment $templating,
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function export(ExportConfig $config): ExportResult
    {
        $sheet = [];
        $headers = \array_column($config->columns, 'name');

        $unprocessedSubmissions = $this->formSubmissionService->getUnprocessed();
        $unprocessedSubmissionsCount = \count($unprocessedSubmissions);

        foreach ($unprocessedSubmissions as $submission) {
            $data = [
                'instance' => $submission->getInstance(),
                'name' => $submission->getName(),
                'locale' => $submission->getLocale(),
                'submission_date' => $submission->getCreated()->format('c'),
                'data' => $submission->getData() ?? [],
            ];

            if ($config->filter && !$this->expressionService->evaluateToBool($config->filter, $data)) {
                continue;
            }

            $line = [];
            foreach ($config->columns as $column) {
                $line[] = $this->renderColumn($column, $data);
            }

            $sheet[] = $line;
        }

        if (0 === \count($sheet)) {
            return new ExportResult($unprocessedSubmissionsCount, 0);
        }

        $extension = $this->determineFormat($config);
        $tempFile = TempFile::create();

        $this->spreadsheetGeneratorService->generateSpreadsheetFile([
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'rows' => [$headers, ...$sheet],
                'name' => 'submissions',
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'submissions',
            SpreadsheetGeneratorServiceInterface::WRITER => $extension,
        ], $tempFile->path);

        if (!empty($config->emailsTo)) {
            $this->sendEmail($tempFile, $config);
        }

        return new ExportResult($unprocessedSubmissionsCount, \count($sheet));
    }

    /**
     * @param array{field?: string, template?: string, block?: string, name?: string} $column
     * @param array<string, mixed>                                                    $data
     */
    private function renderColumn(array $column, array $data): string
    {
        if (!empty($column['field'])) {
            return $this->propertyAccessor->getValue($data, $column['field']) ?? '';
        }

        if (!empty($column['template'])) {
            $template = $this->templating->load($column['template']);

            return !empty($column['block'])
                ? $template->renderBlock($column['block'], \compact('data'))
                : $template->render(\compact('data'));
        }

        return '';
    }

    private function determineFormat(ExportConfig $config): string
    {
        $fileExtension = $config->filename ? \pathinfo($config->filename, PATHINFO_EXTENSION) : null;

        if ($fileExtension && !\in_array($fileExtension, SpreadsheetGeneratorServiceInterface::FORMAT_WRITERS, true)) {
            throw new \InvalidArgumentException("Unsupported file extension: $fileExtension");
        }

        return $config->format ?? $fileExtension ?? SpreadsheetGeneratorServiceInterface::XLSX_WRITER;
    }

    private function sendEmail(TempFile $tempFile, ExportConfig $config): void
    {
        $mailTemplate = $this->mailerService->makeMailTemplate(ExportCommand::MAIL_TEMPLATE);

        foreach ($config->emailsTo as $email) {
            $mailTemplate->addTo($email);
        }

        $mailTemplate
            ->setSubject($config->subject)
            ->setBodyBlock('body')
            ->addAttachment($tempFile->path, \sprintf('%s.%s', $config->filename, $config->format));

        $this->mailerService->sendMailTemplate($mailTemplate);
    }
}

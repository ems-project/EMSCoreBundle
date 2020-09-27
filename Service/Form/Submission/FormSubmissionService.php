<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CoreBundle\Entity\FormSubmission;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use EMS\CoreBundle\Service\TemplateService;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;

final class FormSubmissionService
{
    /** @var FormSubmissionRepository */
    private $repository;

    /** @var Swift_Mailer */
    private $mailer;

    /** @var TemplateService */
    private $templateService;

    const EMAIL_FROM = 'reporting@elasticms.test';
    const NAME_FROM = 'ElasticMS';

    public function __construct(FormSubmissionRepository $repository, Swift_Mailer $mailer, TemplateService $templateService)
    {
        $this->repository = $repository;
        $this->mailer = $mailer;
        $this->templateService = $templateService;
    }

    public function get(string $id): FormSubmission
    {
        $submission = $this->repository->findById($id);

        if (null === $submission) {
            throw new \Exception(sprintf('form submission not found!'));
        }

        return $submission;
    }

    public function createDownload(FormSubmission $formSubmission): string
    {
        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam(\sys_get_temp_dir(), 'ems_form');

        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);

        $rawJson = \json_encode($formSubmission->getData());
        if (is_string($rawJson)) {
            $zip->addFromString('data.json', $rawJson);
        }

        foreach ($formSubmission->getFiles() as $file) {
            $formFile = $file->getFile();

            if (!is_resource($formFile)) {
                continue;
            }

            $formFileContents = stream_get_contents($formFile);
            if (is_string($formFileContents)) {
                $zip->addFromString($file->getFilename(), $formFileContents);
            }
        }

        $zip->close();

        return $tempFile;
    }

    /**
     * @return FormSubmission[]
     */
    public function getUnprocessed(): array
    {
        return $this->repository->findAllUnprocessed();
    }

    /**
     * @param string $formInstance
     * @return FormSubmission[]
     */
    public function getFormInstanceSubmissions(string $formInstance): array
    {
        return $this->repository->findFormInstanceSubmissions($formInstance);
    }

    /**
     * @param array<mixed> $submissions
     * @param string $formInstance
     * @param string $templateId
     * @param array<string> $emails
     */
    public function mailSubmissions(array $submissions, string $formInstance, string $templateId, array $emails): void
    {
        $message = (new Swift_Message());
        $message->setSubject(sprintf('Form submissions for %s', $formInstance))
            ->setFrom(self::EMAIL_FROM, self::NAME_FROM)
            ->setTo($emails)
            ->setBody($this->generateMailBody($submissions, $templateId), 'text/html');

        $this->mailer->send($message);
    }

    public function process(FormSubmission $formSubmission, UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new \Exception('Invalid user passed for processing!');
        }

        $formSubmission->process($user);
        $this->repository->save($formSubmission);
    }

    /**
     * @return array{submission_id: string}
     */
    public function submit(FormSubmissionRequest $submitRequest): array
    {
        $formSubmission = new FormSubmission($submitRequest);

        $this->repository->save($formSubmission);

        return ['submission_id' => $formSubmission->getId()];
    }

    /**
     * @param array<array> $submissions
     * @param string $templateId
     * @return string|null
     */
    private function generateMailBody(array $submissions, string $templateId): ?string
    {
        if ($submissions === []) {
            return 'There are no submissions for this form';
        }

        try {
            $template = $this->templateService->init($templateId)->getTemplate()->getBody();
        } catch (\Exception $e) {
            $template = "Error in body template: " . $e->getMessage();
        }

        $rows = '<tr>
                    <th>Label</th>
                    <th>SubmissionDate</th>
                    <th>DeadlineDate</th>';
        // Generate headers
        foreach ($submissions[0]['data'] as $key => $value) {
            if (is_string($value)) {
                $rows .= '<th>' . $key . '</th>';
            }
        }
        $rows .= '</tr>';

        // Generate rows with values
        foreach ($submissions as $submission) {
            $createdDate = $submission['created'] ? $submission['created']->format('d/m/Y H:i:s') : '';
            $deadlineDate = $submission['deadlineDate'] ? $submission['deadlineDate']->format('d/m/Y H:i:s') : '';

            $rows .= '<tr>';
            $rows .= '<td>' . $submission['label'] . '</td>';
            $rows .= '<td>' . $createdDate . '</td>';
            $rows .= '<td>' . $deadlineDate . '</td>';
            foreach ($submission['data'] as $key => $value) {
                if (is_string($value)) {
                    $rows .= '<td>' . $value . '</td>';
                }
            }
            $rows .= '</tr>';
        }

        return preg_replace('/%replace%/', $rows, $template);
    }
}

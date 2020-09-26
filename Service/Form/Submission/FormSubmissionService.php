<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CoreBundle\Entity\FormSubmission;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use FOS\UserBundle\Mailer\Mailer;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;

final class FormSubmissionService
{
    /** @var FormSubmissionRepository */
    private $repository;

    /** @var Mailer */
    private $mailer;

    public function __construct(FormSubmissionRepository $repository, Swift_Mailer $mailer)
    {
        $this->repository = $repository;
        $this->mailer = $mailer;
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
     * @param array $submissions
     * @param string $formInstance
     */
    public function mailSubmissions(array $submissions, string $formInstance, array $emails): void
    {
        $message = (new Swift_Message());
        $message->setSubject(sprintf('Form submissions for %s', $formInstance))
            ->setFrom('reporting@elasticms.test', 'ElasticMS')
            ->setTo($emails)
            ->setBody($this->generateMailBody($submissions), 'text/html');

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

    private function generateMailBody(array $submissions): string
    {
        if ($submissions === []) {
            return 'There are no submissions for this form';
        }

        $body = '<!DOCTYPE html><html lang="en"><head><title>Submissions</title><meta charset="utf-8"></head><body><table>';
        $body .= '<tr><th>Date</th><th>Label</th><th>DeadlineDate</th><th>Data</th></tr>';
        foreach ($submissions as $submission){
            $data = '';
            $createdDate = $submission['created'] ? $submission['created']->format('d/m/Y H:i:s') : '';
            $deadlineDate = $submission['deadlineDate'] ? $submission['deadlineDate']->format('d/m/Y H:i:s'): '';

            foreach ($submission['data'] as $key => $value) {
                if (is_string($value)) {
                    $data .= $key . ': ' . $value . '<br>';
                }
            }

            $body .= '<tr>
                <td>' . $createdDate . '</td>
                <td>' . $submission['label'] . '</td>
                <td>' . $deadlineDate . '</td>
                <td>' . $data . '</td>
            </tr>';
        }
        $body .= '</table></body></html>';

        return $body;
    }
}

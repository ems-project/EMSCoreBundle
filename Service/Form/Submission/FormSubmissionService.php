<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CoreBundle\Entity\FormSubmission;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use EMS\CoreBundle\Service\TemplateService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

final class FormSubmissionService
{
    /** @var FormSubmissionRepository */
    private $repository;

    /** @var TemplateService */
    private $templateService;

    /** @var Environment */
    private $twig;

    public function __construct(FormSubmissionRepository $repository, TemplateService $templateService, Environment $twig)
    {
        $this->repository = $repository;
        $this->templateService = $templateService;
        $this->twig = $twig;
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
     * @return FormSubmission[]
     */
    public function getAllFormSubmissions(): array
    {
        return $this->repository->findAllFormSubmissions();
    }

    /**
     * @param string $formInstance
     * @return FormSubmission[]
     */
    public function getFormInstanceSubmissions(string $formInstance): array
    {
        return $this->repository->findFormInstanceSubmissions($formInstance);
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
     * @param FormSubmissionRequest $submitRequest
     * @return array{submission_id: string}
     */
    public function submit(FormSubmissionRequest $submitRequest): array
    {
        $formSubmission = new FormSubmission($submitRequest);

        $this->repository->save($formSubmission);

        return ['submission_id' => $formSubmission->getId()];
    }

    /**
     * @param array<FormSubmission> $submissions
     * @param string $templateId
     * @return string
     */
    public function generateMailBody(array $submissions, string $templateId): string
    {
        if ($submissions === []) {
            return 'There are no submissions for this form';
        }

        try {
            $template = $this->twig->createTemplate($this->templateService->init($templateId)->getTemplate()->getBody());
        } catch (\Exception $e) {
            $template = $this->twig->createTemplate("Error in body template: " . $e->getMessage());
        }

        return $template->render(['submissions' => $submissions]);


        /*  <table border="1">
               <tr>
                   <th>Label</th>
                   <th>Submission Date</th>
                   <th>Deadline Date</th>
                   {% for key, value in submissions.0.data %}
                      <th> {{ key }}</th>
                   {% endfor %}
                </tr>
                {% for submission in submissions %}
                    <tr>
                       <td>{{ submission.label }}</td>
                       <td>{{ submission.created|date('Y-m-d') }}</td>
                       <td>{{ submission.deadlineDate|date('Y-m-d') }}</td>
                       {% for key, value in submission.data %}
                          {% if value  is not iterable %}
                             <td>{{ value }}</td>
                          {% endif %}
                       {% endfor %}
                    </tr>
                {% endfor %}
             </table> */
    }
}

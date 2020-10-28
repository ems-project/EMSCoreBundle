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

        $data = $formSubmission->getData();
        $data['id'] = $formSubmission->getId();

        $rawJson = \json_encode($data);
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
        return $this->repository->findFormSubmissions();
    }

    /**
     * @param string|null $formInstance
     * @return FormSubmission[]
     */
    public function getFormSubmissions(?string $formInstance = null): array
    {
        return $this->repository->findFormSubmissions($formInstance);
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
     */
    public function generateMailBody(array $submissions): string
    {
        try {
            if ($submissions === []) {
                return $this->twig->createTemplate('There are no submissions for this form')->render();
            }
            return $this->twig->render('@EMSCore/email/submissions.email.twig', ['submissions' => $submissions]);
        } catch (\Exception $e) {
            return $this->twig->createTemplate("Error in body template: " . $e->getMessage())->render();
        }
    }
}

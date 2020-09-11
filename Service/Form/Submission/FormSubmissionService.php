<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CoreBundle\Entity\FormSubmission;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;


final class FormSubmissionService
{
    /** @var FormSubmissionRepository */
    private $repository;

    public function __construct(FormSubmissionRepository $repository)
    {
        $this->repository = $repository;
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
}

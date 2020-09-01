<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CoreBundle\Entity\FormSubmission;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Filesystem\Filesystem;

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

    public function createDownloadStream(FormSubmission $formSubmission): StreamInterface
    {
        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam(\sys_get_temp_dir(), 'ems_form');

        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);
        $zip->addFromString('data.json', json_encode($formSubmission->getData()));
        $zip->close();

        if (false === $fopen = \fopen($tempFile, 'r')) {
            throw new \Exception('could not open file!');
        }

        return new Stream($fopen);
    }

    public function getUnprocessed(): array
    {
        return $this->repository->findAllUnprocessed();
    }

    public function process(FormSubmission $formSubmission, UserInterface $user): void
    {
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

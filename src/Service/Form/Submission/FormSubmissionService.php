<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\FormSubmissionRepository;
use EMS\CoreBundle\Service\EntityServiceInterface;
use EMS\Helpers\Standard\Json;
use EMS\SubmissionBundle\Entity\FormSubmission;
use EMS\SubmissionBundle\Request\DatabaseRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use ZipStream\ZipStream;

final class FormSubmissionService implements EntityServiceInterface
{
    public function __construct(
        private readonly FormSubmissionRepository $formSubmissionRepository,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly string $templateNamespace
    ) {
    }

    /**
     * @param mixed $context
     *
     * @return FormSubmission[]
     */
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        return $this->formSubmissionRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getById(string $id): FormSubmission
    {
        $submission = $this->formSubmissionRepository->findById($id);

        if (null === $submission) {
            throw new \Exception(\sprintf('form submission not found!'));
        }

        return $submission;
    }

    public function createDownload(string $formSubmission): StreamedResponse
    {
        return $this->createDownloadForMultiple([$formSubmission]);
    }

    /**
     * @param array<string> $formSubmissionIds
     *
     * @throws \Exception
     */
    public function createDownloadForMultiple(array $formSubmissionIds): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($formSubmissionIds) {
            $zip = new ZipStream(outputName: 'submissionData.zip');

            foreach ($formSubmissionIds as $formSubmissionId) {
                $formSubmission = $this->getById($formSubmissionId);
                $data = $formSubmission->getData();

                $rawJson = Json::encode($data, false, true);
                if (\is_string($rawJson)) {
                    $zip->addFile($formSubmissionId.'/data.json', $rawJson);
                }

                foreach ($formSubmission->getFiles() as $file) {
                    if ($streamRead = $file->getFile()) {
                        $zip->addFileFromStream($formSubmissionId.'/'.$file->getFilename(), $streamRead);
                    } else {
                        exit('Could not open stream for reading');
                    }
                }
            }

            $zip->finish();
        });

        return $response;
    }

    /**
     * @param array<string> $formSubmissionIds
     *
     * @return array<mixed> $config
     */
    public function generateExportConfig(array $formSubmissionIds): array
    {
        $config = [];
        $sheets = [];

        foreach ($formSubmissionIds as $formSubmissionId) {
            $formSubmission = $this->getById($formSubmissionId);
            /** @var array<mixed> $data */
            $data = $formSubmission->getData();
            $data = \array_filter($data, fn ($value) => \is_string($value));
            $data['id'] = $formSubmission->getId();
            $data['form'] = $formSubmission->getName();
            $data['instance'] = $formSubmission->getInstance();
            $data['locale'] = $formSubmission->getLocale();
            $data['created'] = $formSubmission->getCreated()->format('Y-m-d H:i:s');
            $expireDate = $formSubmission->getExpireDate();
            $data['deadline'] = null === $expireDate ? '' : $expireDate->format('Y-m-d');

            $sheetName = $formSubmission->getName();
            if (!\key_exists($sheetName, $sheets)) {
                $titles = [];
                foreach ($data as $key => $value) {
                    $titles[] = $key;
                }
                $sheets[$sheetName] = [$titles];
            }
            $sheets[$sheetName] = \array_merge($sheets[$sheetName], [$data]);
        }

        $config['sheets'] = [];
        foreach ($sheets as $key => $value) {
            $config['sheets'][] = [
              'name' => $key,
              'rows' => $value,
            ];
        }

        return $config;
    }

    /**
     * @return FormSubmission[]
     */
    public function getUnprocessed(): array
    {
        return $this->formSubmissionRepository->findAllUnprocessed();
    }

    /**
     * @return FormSubmission[]
     */
    public function getAllFormSubmissions(): array
    {
        return $this->formSubmissionRepository->findFormSubmissions();
    }

    /**
     * @return FormSubmission[]
     */
    public function getFormSubmissions(string $formInstance = null): array
    {
        return $this->formSubmissionRepository->findFormSubmissions($formInstance);
    }

    public function process(string $id, UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new \Exception('Invalid user passed for processing!');
        }

        $formSubmission = $this->getById($id);
        $session = $this->requestStack->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            throw new \RuntimeException('Unexpected non FlashBag aware session');
        }
        $session->getFlashBag()->add('notice', $this->translator->trans('form_submissions.process.success', ['%id%' => $formSubmission->getId()], 'EMSCoreBundle'));

        $formSubmission->process($user->getUsername());
        $this->formSubmissionRepository->save($formSubmission);
    }

    /**
     * @param array<string> $ids
     *
     * @throws \Exception
     */
    public function processByIds(array $ids, UserInterface $user): void
    {
        if (!$user instanceof User) {
            throw new \Exception('Invalid user passed for processing!');
        }

        foreach ($ids as $id) {
            $formSubmission = $this->getById($id);
            $formSubmission->process($user->getUsername());
            $this->formSubmissionRepository->persist($formSubmission);
            $session = $this->requestStack->getSession();
            if (!$session instanceof FlashBagAwareSessionInterface) {
                throw new \RuntimeException('Unexpected non FlashBag aware session');
            }
            $session->getFlashBag()->add('notice', $this->translator->trans('form_submissions.process.success', ['%id%' => $id], 'EMSCoreBundle'));
        }

        $this->formSubmissionRepository->flush();
    }

    /**
     * @return array{submission_id: string}
     */
    public function submit(DatabaseRequest $submitRequest): array
    {
        $formSubmission = new FormSubmission($submitRequest);

        $this->formSubmissionRepository->save($formSubmission);

        return ['submission_id' => $formSubmission->getId()];
    }

    public function removeExpiredSubmissions(): int
    {
        return $this->formSubmissionRepository->removeAllOutdatedSubmission();
    }

    /**
     * @param array<FormSubmission> $submissions
     */
    public function generateMailBody(array $submissions): string
    {
        try {
            if ([] === $submissions) {
                return $this->twig->createTemplate('There are no submissions for this form')->render();
            }

            return $this->twig->render("@$this->templateNamespace/email/submissions.email.twig", ['submissions' => $submissions]);
        } catch (\Exception $e) {
            return $this->twig->createTemplate('Error in body template: '.$e->getMessage())->render();
        }
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function getEntityName(): string
    {
        return 'formSubmission';
    }

    /**
     * @return string[]
     */
    public function getAliasesName(): array
    {
        return [];
    }

    public function count(string $searchValue = '', $context = null): int
    {
        return $this->formSubmissionRepository->countAllUnprocessed($searchValue);
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        throw new \RuntimeException('getByItemName method not yet implemented');
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}

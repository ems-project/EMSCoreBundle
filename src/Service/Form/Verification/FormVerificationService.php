<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Verification;

use EMS\CoreBundle\Entity\FormVerification;
use EMS\CoreBundle\Repository\FormVerificationRepository;
use Symfony\Component\HttpFoundation\Response;

final class FormVerificationService
{
    /** @var FormVerificationRepository */
    private $repository;

    public function __construct(FormVerificationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array{code: string}
     */
    public function create(CreateVerificationRequest $request): array
    {
        $formVerification = $this->repository->create(new FormVerification($request->getValue()));

        return ['code' => $formVerification->getCode()];
    }

    /**
     * @return array{code: string}
     */
    public function get(string $value): array
    {
        $formVerification = $this->repository->get($value);

        if (null === $formVerification) {
            throw new FormVerificationException('Not found!', Response::HTTP_NOT_FOUND);
        }

        return ['code' => $formVerification->getCode()];
    }
}

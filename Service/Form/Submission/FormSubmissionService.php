<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use EMS\CoreBundle\Entity\FormSubmission;

final class FormSubmissionService
{
    /** @var ObjectManager */
    private $em;

    public function __construct(Registry $registry)
    {
        $this->em = $registry->getManager();
    }

    /**
     * @return array{submission_id: string}
     */
    public function submit(FormSubmissionRequest $submitRequest): array
    {
        $formSubmission = new FormSubmission($submitRequest);

        $this->em->persist($formSubmission);
        $this->em->flush();

        return ['submission_id' => $formSubmission->getId()];
    }
}

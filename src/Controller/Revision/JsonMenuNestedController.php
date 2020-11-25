<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectRepository;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class JsonMenuNestedController extends AbstractController
{
    /** @var RevisionService */
    private $revisionService;
    /** @var ObjectRepository|FieldTypeRepository */
    private $fieldTypeRepository;

    public function __construct(RevisionService $revisionService, Registry $doctrine)
    {
        $this->revisionService = $revisionService;
        $this->fieldTypeRepository = $doctrine->getRepository(FieldType::class);
    }

    /**
     * @Route("/data/draft/edit/{revisionId}/nested-modal/{fieldTypeId}", name="revision.edit.nested-modal", methods={"POST"})
     */
    public function modal(Request $request, int $revisionId, int $fieldTypeId): JsonResponse
    {
        if (null === $revision = $this->revisionService->find($revisionId)) {
            throw new NotFoundHttpException('Unknown revision');
        }

        if (null === $fieldType = $this->fieldTypeRepository->find($fieldTypeId)) {
            throw new NotFoundException('Unknown fieldtype');
        }

        $content = $request->getContent();
        $data = \is_string($content) ? \json_decode($content, true) : [];

        $form = $this->createForm(RevisionJsonMenuNestedType::class, $data, [
            'field_type' => $fieldType,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $object = $form->getData();
            unset($object['label']);
        }

        return new JsonResponse(\array_filter([
            'object' => $object ?? null,
            'label' => $form->get('label')->getData(),
            'html' => $this->renderView('@EMSCore/data/json-menu-nested.html.twig', [
                'form' => $form->createView(),
                'revision' => $revision,
                'fieldType' => $fieldType,
            ]),
        ]));
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectRepository;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CoreBundle\Core\Revision\RawDataTransformer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionJsonMenuNestedType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Service\DataService;
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
    private DataService $dataService;

    public function __construct(RevisionService $revisionService, Registry $doctrine, DataService $dataService)
    {
        $this->revisionService = $revisionService;
        $this->dataService = $dataService;
        $this->fieldTypeRepository = $doctrine->getRepository(FieldType::class);
    }

    /**
     * @Route("/data/revisions-modal/{fieldType}", name="revision.edit.nested-preview-modal", methods={"POST"})
     */
    public function modalPreview(Request $request, FieldType $fieldType): JsonResponse
    {
        $rawData = [];

        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();
            $rawData = \is_string($requestContent) ? \json_decode($requestContent, true) : [];
        }

        $subField = null;
        foreach ($fieldType->getChildren() as $child) {
            if (!$child instanceof FieldType || $child->getDeleted()) {
                continue;
            }
            if ($child->getName() === $rawData['type'] ?? null) {
                $subField = $child;
                break;
            }
        }

        if (!$subField instanceof FieldType) {
            return new JsonResponse(\array_filter([
                'html' => $this->renderView('@EMSCore/data/json-menu-nested-json-preview.html.twig', [
                    'rawData' => $rawData['object'] ?? [],
                ]),
            ]));
        }

        $contentType = new ContentType();
        $contentType->setFieldType($subField);
        $revision = new Revision();
        $revision->setRawData($rawData['object']);
        $revision->setContentType($contentType);
        $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $rawData['object']]);
        $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));

        return new JsonResponse(\array_filter([
            'html' => $this->renderView('@EMSCore/data/json-menu-nested-preview.html.twig', [
                'dataFields' => $dataFields,
                'rawData' => $rawData['object'] ?? [],
            ]),
        ]));
    }

    /**
     * @Route("/data/draft/edit/{revisionId}/nested-modal/{fieldTypeId}", name="revision.edit.nested-modal", methods={"POST"})
     */
    public function modal(Request $request, int $revisionId, int $fieldTypeId): JsonResponse
    {
        if (null === $revision = $this->revisionService->find($revisionId)) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $fieldType = $this->fieldTypeRepository->find($fieldTypeId);

        if (null === $fieldType || !$fieldType instanceof FieldType) {
            throw new NotFoundException('Unknown fieldtype');
        }

        $label = null;
        $rawData = [];

        if ('json' === $request->getContentType()) {
            $requestContent = $request->getContent();
            $rawData = \is_string($requestContent) ? \json_decode($requestContent, true) : [];
            $label = $rawData['label'] ?? null;
        }

        $data = RawDataTransformer::transform($fieldType, $rawData);
        $data['label'] = $label;

        $form = $this->createForm(RevisionJsonMenuNestedType::class, $data, ['field_type' => $fieldType]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $object = RawDataTransformer::reverseTransform($fieldType, $formData);
        }

        return new JsonResponse(\array_filter([
            'object' => $object ?? null,
            'label' => $form->get('label')->getData(),
            'html' => $this->renderView('@EMSCore/data/json-menu-nested.html.twig', [
                'form' => $form->createView(),
                'fieldType' => $fieldType,
            ]),
        ]));
    }
}

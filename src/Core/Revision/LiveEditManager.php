<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\DataField\CheckboxFieldType;
use EMS\CoreBundle\Form\Form\LiveEditFieldType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class LiveEditManager
{
    private AuthorizationCheckerInterface $authorizationChecker;
    private RevisionService $revisionService;
    private DataService $dataService;
    private UserService $userService;
    protected FormFactoryInterface $formFactory;
    private ContentTypeService $contentTypeService;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, RevisionService $revisionService, DataService $dataService, UserService $userService, FormFactoryInterface $formFactory, ContentTypeService $contentTypeService)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->revisionService = $revisionService;
        $this->dataService = $dataService;
        $this->userService = $userService;
        $this->formFactory = $formFactory;
        $this->contentTypeService = $contentTypeService;
    }

    /**
     * @param array<string> $rawdata
     * @param array<string> $fields
     */
    public function isEditableByUser(ContentType $contentType, array $rawdata, array $fields): bool
    {
        if (null === $contentType->getFieldType()) {
            throw new \RuntimeException('Field type is unset!');
        }

        if ($this->authorizationChecker->isGranted($contentType->getFieldType()->getFieldsRoles())) {
            return true;
        }

        foreach ($fields as $field) {

            $fieldType = $this->contentTypeService->getFieldTypeByRawPath($contentType->getFieldType(), $this->getRawPath($rawdata, $field));
            if (null !== $fieldType && $this->authorizationChecker->isGranted($fieldType->getFieldsRoles())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $rawdata
     * @param array<string> $fields
     * @return array<string>
     */
    public function getFormsFields(ContentType $contentType, array $rawdata, array $fields): array
    {
        if (null === $contentType->getFieldType()) {
            throw new \RuntimeException('Field type is unset!');
        }

        $forms = [];
        foreach ($fields as $field) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $rawPath = $this->getRawPath($rawdata, $field);
            if (\count($rawPath) > 0) {
                $fieldType = $this->contentTypeService->getFieldTypeByRawPath($contentType->getFieldType(), $rawPath);
                if (null !== $fieldType && $this->authorizationChecker->isGranted($fieldType->getFieldsRoles())) {
                    $options = \array_merge(['metadata' => $fieldType]);
                    if ($fieldType->getType() === CheckboxFieldType::class) {
                        $options = $propertyAccessor->getValue($rawPath, $field) == true ? \array_merge($options, [ 'data' => $rawPath, 'attr' => ['checked' => true]]) : $options;
                    } else {
                        $options = \array_merge($options, ['data'  => $propertyAccessor->getValue($rawPath, $field) ]);
                    }

                    $form = $this->formFactory->createBuilder()
                        ->add($fieldType->getName(), $fieldType->getType(), $options)->getForm();
                    $forms[$field] = $form;
                }
            }
        }
        return $forms;
    }

    /**
     * @param array<string> $rawdata
     * @return array<string>
     */
    private function getRawPath(array $rawdata, string $field): array
    {
        $path = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $propertyAccessor->setValue($path, $field, $propertyAccessor->getValue($rawdata,$field));

        return $path;
    }

    public function createNewDraft(Revision $revision): Revision
    {
        return $this->dataService->initNewDraft($revision->getContentTypeName(), $revision->getOuuid(), null, $this->userService->getCurrentUser());
    }

    public function getRevision(EMSLink $EMSLink): ?Revision
    {
        return $this->revisionService->getByEmsLink($EMSLink);
    }
}

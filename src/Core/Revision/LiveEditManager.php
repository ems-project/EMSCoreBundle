<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Revision;
//use EMS\CoreBundle\Form\DataField\CheckboxFieldType;
//use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class LiveEditManager
{
    private AuthorizationCheckerInterface $authorizationChecker;
    private RevisionService $revisionService;
    private DataService $dataService;
    private UserService $userService;

    //const TYPES = [ TextStringFieldType::class, CheckboxFieldType::class ]; @TODO needed or not (maybe not)

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, RevisionService $revisionService, DataService $dataService, UserService $userService)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->revisionService = $revisionService;
        $this->dataService = $dataService;
        $this->userService = $userService;
    }

    /**
     * @param array<string> $fields
     */
    public function isEditableByUser(FieldType $fieldType, array $fields): bool
    {
        if ($this->authorizationChecker->isGranted($fieldType->getFieldsRoles())) {
            return true;
        }

        foreach ($fields as $field) {
            $matches = [];
            \preg_match_all('/\[(.*?)\]/m', $field, $matches, PREG_PATTERN_ORDER);
            $child = $fieldType->findChildByName($matches[1][\count($matches[1]) - 1]);
            if (null !== $child && $this->authorizationChecker->isGranted($child->getFieldsRoles())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string> $fields
     * @return array<string>
     */
    public function getFormsFields(FieldType $fieldType, array $fields): array
    {
        $forms = [];
        foreach ($fields as $field) {
            $matches = [];
            \preg_match_all('/\[(.*?)\]/m', $field, $matches, PREG_PATTERN_ORDER);
            $child= $fieldType->findChildByName($matches[1][\count($matches[1]) - 1]);
            if (null !== $child && $this->authorizationChecker->isGranted($fieldType->getFieldsRoles())) {
                //@TODO create Form for each field
                $forms[$field] = "";
            }
        }
        return $forms;
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

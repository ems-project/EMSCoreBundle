<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Data\Condition\DateInFuture;
use EMS\CoreBundle\Form\Data\Condition\InMyCircles;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\EntityServiceInterface;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DraftInProgress implements EntityServiceInterface
{
    public const DISCARD_SELECTED_DRAFT = 'DISCARD_SELECTED_DRAFT';
    private RevisionRepository $revisionRepository;
    private UserService $userService;
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(RevisionRepository $revisionRepository, AuthorizationCheckerInterface $authorizationChecker, UserService $userService)
    {
        $this->revisionRepository = $revisionRepository;
        $this->userService = $userService;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context && !$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->revisionRepository->getDraftInProgress($from, $size, $orderField, $orderDirection, $searchValue, $context);
    }

    public function getEntityName(): string
    {
        return 'draft_in_progress';
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
        if (null !== $context && !$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        return $this->revisionRepository->countDraftInProgress($searchValue, $context);
    }

    public function getDataTable(string $ajaxUrl, ?ContentType $contentType): EntityTable
    {
        $table = new EntityTable($this, $ajaxUrl, $contentType);
        $table->setRowActionsClass('pull-right');
        $table->setLabelAttribute('label');
        $table->setDefaultOrder('modified', 'desc');
        $table->addColumnDefinition(new DatetimeTableColumn('revision.draft-in-progress.column.modified', 'draftSaveDate'));
        $table->addColumnDefinition(new UserTableColumn('revision.draft-in-progress.column.auto-save-by', 'autoSaveBy'));
        $table->addColumn('revision.draft-in-progress.column.label', 'label')->setOrderField('labelField');
        $lockUntil = new DatetimeTableColumn('revision.draft-in-progress.column.locked', 'lockUntil');
        $condition = new DateInFuture('lockUntil');
        $lockUntil->addCondition($condition);
        $table->addColumnDefinition($lockUntil);
        $lockBy = new UserTableColumn('revision.draft-in-progress.column.locked-by', 'lockBy');
        $lockBy->addCondition($condition);
        $table->addColumnDefinition($lockBy);
        $inMyCircles = new InMyCircles($this->userService, $this->authorizationChecker);
        $table->addDynamicItemGetAction(Routes::EDIT_REVISION, 'revision.draft-in-progress.column.edit-draft', 'pencil', [
            'revisionId' => 'id',
        ])->addCondition($inMyCircles)->setButtonType('primary');
        $table->addDynamicItemGetAction(Routes::VIEW_REVISIONS, 'revision.draft-in-progress.column.view-revision', '', [
            'type' => 'contentType.name',
            'ouuid' => 'ouuid',
        ])->addCondition(new NotEmpty('ouuid'));
        $table->addDynamicItemPostAction(Routes::DISCARD_DRAFT, 'revision.draft-in-progress.column.discard-draft', 'trash', 'revision.draft-in-progress.column.confirm-discard-draft', [
           'revisionId' => 'id',
        ])->addCondition($inMyCircles)->setButtonType('outline-danger');

        if (null !== $contentType && (null === $contentType->getCirclesField() || '' === $contentType->getCirclesField())) {
            $table->addTableAction(self::DISCARD_SELECTED_DRAFT, 'fa fa-trash', 'revision.draft-in-progress.action.discard-selected-draft', 'revision.draft-in-progress.action.discard-selected-confirm')
                ->setCssClass('btn btn-outline-danger');
        }

        return $table;
    }

    public function getByItemName(string $name): ?EntityInterface
    {
        return $this->revisionRepository->findOneById(\intval($name));
    }

    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        throw new \RuntimeException('updateEntityFromJson method not yet implemented');
    }

    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        throw new \RuntimeException('createEntityFromJson method not yet implemented');
    }

    public function deleteByItemName(string $name): string
    {
        throw new \RuntimeException('deleteByItemName method not yet implemented');
    }
}

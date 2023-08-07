<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Revision\DraftInProgress;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Data\Condition\DateInFuture;
use EMS\CoreBundle\Form\Data\Condition\InMyCircles;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionDraftsDataTableType extends AbstractEntityTableType
{
    public function __construct(
        DraftInProgress $draftInProgress,
        private readonly ContentTypeService $contentTypeService,
        private readonly UserService $userService
    ) {
        parent::__construct($draftInProgress);
    }

    public function build(EntityTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();

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
        $inMyCircles = new InMyCircles($this->userService);
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
            $table->addTableAction(DraftInProgress::DISCARD_SELECTED_DRAFT, 'fa fa-trash', 'revision.draft-in-progress.action.discard-selected-draft', 'revision.draft-in-progress.action.discard-selected-confirm')
                ->setCssClass('btn btn-outline-danger');
        }
    }

    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_USER];
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }
}

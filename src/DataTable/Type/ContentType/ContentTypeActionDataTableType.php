<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\ActionService;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeActionDataTableType extends AbstractEntityTableType
{
    public function __construct(
        ActionService $entityService,
        private readonly ContentTypeService $contentTypeService
    ) {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();

        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumnDefinition(new BoolTableColumn('action.index.column.public', 'public'));
        $table->addColumn('action.index.column.name', 'name');
        $table->addColumn('action.index.column.label', 'label')
            ->setItemIconCallback(fn (Template $action) => $action->getIcon());
        $table->addColumn('action.index.column.type', 'renderOption');
        $table->addItemGetAction('ems_core_action_edit', 'action.actions.edit', 'pencil', ['contentType' => $contentType]);
        $table->addItemPostAction('ems_core_action_delete', 'action.actions.delete', 'trash', 'action.actions.delete_confirm', ['contentType' => $contentType->getId()]);
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'action.actions.delete_selected', 'action.actions.delete_selected_confirm');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }
}

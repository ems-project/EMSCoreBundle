<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Environment\EnvironmentManagedEntityService;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EnvironmentDataTableType extends AbstractEntityTableType
{
    public function __construct(
        EnvironmentManagedEntityService $entityService,
        private readonly string $templateNamespace
    ) {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $template = "@$this->templateNamespace/environment/index.html.twig";

        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumnDefinition(new TemplateBlockTableColumn('environment.index.column.label', 'column_label', $template));
        $table->addColumn('environment.index.column.name', 'name');
        $table->addColumn('environment.index.column.alias', 'alias');
        $table->addColumnDefinition(new TemplateBlockTableColumn('environment.index.column.index', 'column_indexes', $template));
        $table->addColumn('environment.index.column.total_indexed_label', 'total');
        $table->addColumn('environment.index.column.total_in_ems', 'counter');
        $table->addColumn('environment.index.column.total_mark_has_deleted', 'deletedRevision');
        $table->addItemGetAction('environment.rebuild', 'environment.actions.rebuild_button', 'recycle', ['id' => 'id'])
            ->setDynamic(true)
            ->setButtonType('primary');
        $table->addItemGetAction('environment.view', 'environment.actions.view_button', 'eye', ['id' => 'id'])->setDynamic(true);
        $table->addItemGetAction('environment.edit', 'environment.actions.edit_button', 'edit', ['id' => 'id'])->setDynamic(true);
        $table->addItemPostAction('environment.remove', 'environment.actions.delete', 'trash', 'environment.actions.delete_confirm', ['id' => 'id'])
            ->setDynamic(true)
            ->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'environment.actions.delete_selected', 'environment.actions.delete_selected_confirm');
        $table->setDefaultOrder('orderKey');
    }

    /**
     * @param array{'managed': bool} $options
     *
     * @return array{'managed': bool}
     */
    public function getContext(array $options): array
    {
        return $options;
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver
            ->setRequired(['managed'])
            ->setAllowedTypes('managed', ['bool'])
        ;
    }
}

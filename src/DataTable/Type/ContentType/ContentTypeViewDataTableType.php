<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Form\Data\TranslationTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeViewDataTableType extends AbstractEntityTableType
{
    public function __construct(
        ViewManager $entityService,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace
    ) {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.public', 'public', "@$this->templateNamespace/view/columns.html.twig"));
        $table->addColumn('view.index.column.name', 'name');
        $table->addColumn('view.index.column.label', 'label')->setItemIconCallback(fn (View $view) => $view->getIcon() ?? '');
        $table->addColumnDefinition(new TranslationTableColumn('view.index.column.type', 'type', EMSCoreBundle::TRANS_FORM_DOMAIN));

        $definitionColumn = new TranslationTableColumn('view.index.column.definition', 'definition', EMSCoreBundle::TRANS_FORM_DOMAIN);
        $definitionColumn->setKeyPrefix('ems.view.definition');
        $table->addColumnDefinition($definitionColumn);

        $table->addItemGetAction(Routes::VIEW_EDIT, 'view.actions.edit', 'pencil');
        $table->addItemPostAction(Routes::VIEW_DUPLICATE, 'view.actions.duplicate', 'pencil', 'view.actions.duplicate_confirm');

        $defineAction = $table->addItemActionCollection('view.actions.define.title', 'gear');
        $defineAction->addItemPostAction(Routes::VIEW_DEFINE, 'view.actions.define.default_overview', 'list', null, ['definition' => ViewDefinition::DEFAULT_OVERVIEW->value]);
        $defineAction->addItemPostAction(Routes::VIEW_UNDEFINE, 'view.actions.undefine', 'eraser', null);

        $table->addItemPostAction(Routes::VIEW_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'view.actions.delete_selected', 'view.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');
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

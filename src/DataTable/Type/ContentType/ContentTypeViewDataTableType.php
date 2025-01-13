<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class ContentTypeViewDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(
        ViewManager $entityService,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();

        $this->addColumnsOrderLabelName($table);
        $table->getColumnByName('label')?->setItemIconCallback(fn (View $view) => $view->getIcon());

        $table->addColumnDefinition(
            new TemplateBlockTableColumn(
                label: t('field.type', [], 'emsco-core'),
                blockName: 'contentTypeViewType',
                template: "@$this->templateNamespace/datatable/template_block_columns.html.twig"
            )
        );
        $table->addColumnDefinition(
            new TemplateBlockTableColumn(
                label: t('field.definition', [], 'emsco-core'),
                blockName: 'contentTypeViewDefinition',
                template: "@$this->templateNamespace/datatable/template_block_columns.html.twig"
            )
        );

        $table->addColumnDefinition(new BoolTableColumn(t('field.public_access', [], 'emsco-core'), 'public'));

        $this->addItemEdit($table, Routes::ADMIN_CONTENT_TYPE_VIEW_EDIT);
        $table->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_VIEW_DUPLICATE,
            labelKey: t('action.duplicate', [], 'emsco-core'),
            icon: 'pencil',
            messageKey: t('action.confirmation', [], 'emsco-core')
        );

        $defineAction = $table->addItemActionCollection(t('action.define', [], 'emsco-core'), 'gear');
        $defineAction->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_VIEW_DEFINE,
            labelKey: t('core.content_type.view_define', ['define' => ViewDefinition::DEFAULT_OVERVIEW->value], 'emsco-core'),
            icon: ViewDefinition::DEFAULT_OVERVIEW->getIcon(),
            routeParameters: ['definition' => ViewDefinition::DEFAULT_OVERVIEW->value]
        );
        $defineAction->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_VIEW_UNDEFINE,
            labelKey: t('core.dashboard.define', ['define' => null], 'emsco-core'),
            icon: 'eraser'
        );

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemDelete($table, 'content_type_view', Routes::ADMIN_CONTENT_TYPE_VIEW_DELETE)
            ->addTableToolbarActionAdd($table, Routes::ADMIN_CONTENT_TYPE_VIEW_ADD, ['contentType' => $contentType->getId()])
            ->addTableActionDelete($table, 'content_type_view');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    #[\Override]
    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\I18nService;

use function Symfony\Component\Translation\t;

class I18nDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(
        I18nService $i18NService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($i18NService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table
            ->setDefaultOrder('identifier')
            ->setLabelAttribute('identifier');

        $table->addColumn(t('field.key', [], 'emsco-core'), 'identifier');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.translations', [], 'emsco-core'),
            blockName: 'i18nTranslations',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'progress'
        ));

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemEdit($table, Routes::I18N_EDIT)
            ->addItemDelete($table, 'i18n', Routes::I18N_DELETE)
            ->addTableToolbarActionAdd($table, Routes::I18N_ADD)
            ->addTableActionDelete($table, 'i18n');
    }

    #[\Override]
    public function getLoadMaxRows(): int
    {
        return 50;
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Environment;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class EnvironmentDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($this->environmentService);
    }

    public function build(EntityTable $table): void
    {
        $managed = (bool) $table->getContext()['managed'];

        if ($managed) {
            $table->setDefaultOrder('orderKey');
            $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey');
        } else {
            $table->setDefaultOrder('label');
        }

        $table->setLabelAttribute('label');
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.label', [], 'emsco-core'),
            blockName: 'environmentName',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig"
        ));

        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.alias', [], 'emsco-core'), 'alias');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.index', [], 'emsco-core'),
            blockName: 'environmentIndex',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'label'
        ));

        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_build', [], 'emsco-core'),
            attribute: 'buildDate'
        ));

        $table->addColumn(t('field.total_elastic', [], 'emsco-core'), 'total');
        if ($managed) {
            $table->addColumn(t('field.total_ems', [], 'emsco-core'), 'counter');
            $table->addColumn(t('field.total_deleted', [], 'emsco-core'), 'deletedRevision');

            $table->addItemGetAction(
                route: Routes::ADMIN_ENVIRONMENT_REBUILD,
                labelKey: t('action.rebuild', [], 'emsco-core'),
                icon: 'recycle'
            )->setButtonType('primary');
        }

        $table->addItemGetAction(
            route: Routes::ADMIN_ENVIRONMENT_VIEW,
            labelKey: t('action.view', [], 'emsco-core'),
            icon: 'eye'
        );

        $deleteType = $managed ? 'environment' : 'environment_external';

        $this
            ->addItemEdit($table, Routes::ADMIN_ENVIRONMENT_EDIT)
            ->addItemDelete($table, $deleteType, Routes::ADMIN_ENVIRONMENT_REMOVE);

        if ($managed) {
            $this
                ->addTableToolbarActionAdd($table, Routes::ADMIN_ENVIRONMENT_ADD)
                ->addTableActionDelete($table, $deleteType);
        }
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
            ->setDefaults(['stats' => true])
            ->setRequired(['managed'])
            ->setAllowedTypes('managed', ['bool'])
        ;
    }
}

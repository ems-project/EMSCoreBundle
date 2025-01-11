<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Environment;

use EMS\CoreBundle\Core\DataTable\ArrayDataSource;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AliasService;

use function Symfony\Component\Translation\t;

class EnvironmentManagedAliasDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public function __construct(
        private readonly AliasService $aliasService,
        private readonly string $templateNamespace,
    ) {
    }

    #[\Override]
    public function build(QueryTable $table): void
    {
        $table->setDefaultOrder('name')->setLabelAttribute('name');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.label', [], 'emsco-core'),
            blockName: 'environmentName',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'environmentLabel'
        ));

        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.alias', [], 'emsco-core'), 'alias');
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.indexes', [], 'emsco-core'),
            blockName: 'environmentIndexesModal',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'countIndexes',
        ));
        $table->addColumn(t('field.total', [], 'emsco-core'), 'total');

        $this
            ->addTableToolbarActionAdd($table, Routes::ADMIN_MANAGED_ALIAS_ADD)
            ->addItemEdit($table, Routes::ADMIN_MANAGED_ALIAS_EDIT);

        $table->addDynamicItemPostAction(
            route: Routes::ADMIN_ELASTIC_ALIAS_ATTACH,
            labelKey: t('action.attach', [], 'emsco-core'),
            icon: 'plus',
            messageKey: t('type.confirm', ['type' => 'attach_alias'], 'emsco-core'),
            routeParameters: ['name' => 'name']
        )->setButtonType('primary');

        $this->addItemDelete($table, 'managed_alias', Routes::ADMIN_MANAGED_ALIAS_DELETE);
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    #[\Override]
    public function getQueryName(): string
    {
        return 'managedAlias';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $dataSource = $this->getDataSource($searchValue);

        if (null !== $orderField) {
            return $dataSource->sort(\sprintf('[%s]', $orderField), $orderDirection)->getData($from, $size);
        }

        return $dataSource->getData($from, $size);
    }

    #[\Override]
    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return $this->getDataSource($searchValue)->count();
    }

    private function getDataSource(string $searchValue): ArrayDataSource
    {
        static $dataSource = null;

        if (null === $dataSource) {
            $managedAliases = $this->aliasService->getManagedAliases();

            $dataSource = new ArrayDataSource(\array_map(static fn (ManagedAlias $managedAlias) => [
                'id' => $managedAlias->getId(),
                'name' => $managedAlias->getName(),
                'label' => $managedAlias->getLabel(),
                'color' => $managedAlias->getColor(),
                'alias' => $managedAlias->getAlias(),
                'indexes' => $managedAlias->getIndexes(),
                'countIndexes' => \count($managedAlias->getIndexes()),
                'total' => $managedAlias->getTotal(),
            ], $managedAliases));
        }

        return $dataSource->search($searchValue);
    }
}

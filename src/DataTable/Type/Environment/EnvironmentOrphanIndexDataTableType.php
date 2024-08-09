<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Environment;

use EMS\CoreBundle\Core\DataTable\ArrayDataSource;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AliasService;

use function Symfony\Component\Translation\t;

class EnvironmentOrphanIndexDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public const ACTION_DELETE_ALL = 'delete_all';

    public function __construct(private readonly AliasService $aliasService)
    {
    }

    public function build(QueryTable $table): void
    {
        $table->setIdField('name');
        $table->setDefaultOrder('name')->setLabelAttribute('name');

        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.count', [], 'emsco-core'), 'count');

        $table->addDynamicItemGetAction(
            route: 'elasticsearch.alias.add',
            labelKey: t('action.add_alias', [], 'emsco-core'),
            icon: 'plus',
            routeParameters: ['name' => 'name']
        )->setButtonType('primary');

        $table->addDynamicItemPostAction(
            route: Routes::ADMIN_ELASTIC_ORPHAN_DELETE,
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => 'orphan_index'], 'emsco-core'),
            routeParameters: ['name' => 'name']
        )->setButtonType('outline-danger');

        $this->addTableActionDelete($table, 'orphan_index');
        $table->addMassAction(
            name: self::ACTION_DELETE_ALL,
            label: t('action.delete_all', [], 'emsco-core'),
            icon: 'fa fa-eraser',
            confirmationKey: t('type.confirm', ['type' => 'delete_all_orphan_index'], 'emsco-core')
        );
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    public function getQueryName(): string
    {
        return 'EnvironmentOrphanIndex';
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $dataSource = $this->getDataSource($searchValue);

        if (null !== $orderField) {
            return $dataSource->sort(\sprintf('[%s]', $orderField), $orderDirection)->data;
        }

        return $dataSource->data;
    }

    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return $this->getDataSource($searchValue)->count();
    }

    private function getDataSource(string $searchValue): ArrayDataSource
    {
        static $dataSource = null;

        if (null === $dataSource) {
            $dataSource = new ArrayDataSource($this->aliasService->getOrphanIndexes());
        }

        return $dataSource->search($searchValue);
    }
}

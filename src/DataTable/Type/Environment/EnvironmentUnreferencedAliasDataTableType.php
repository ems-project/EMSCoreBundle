<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Environment;

use EMS\CoreBundle\Core\DataTable\ArrayDataSource;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AliasService;

use function Symfony\Component\Translation\t;

class EnvironmentUnreferencedAliasDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    public function __construct(
        private readonly AliasService $aliasService,
        private readonly string $templateNamespace,
    ) {
    }

    #[\Override]
    public function build(QueryTable $table): void
    {
        $table->setIdField('name');
        $table->setDefaultOrder('name')->setLabelAttribute('name');

        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.indexes', [], 'emsco-core'),
            blockName: 'environmentIndexesModal',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'countIndexes'
        ));
        $table->addColumn(t('field.total', [], 'emsco-core'), 'total');

        $table->addDynamicItemPostAction(
            route: Routes::ADMIN_ELASTIC_ALIAS_ATTACH,
            labelKey: t('action.attach', [], 'emsco-core'),
            icon: 'plus',
            messageKey: t('type.confirm', ['type' => 'attach_alias'], 'emsco-core'),
            routeParameters: ['name' => 'name']
        )->setButtonType('primary');

        $table->addDynamicItemPostAction(
            route: Routes::ADMIN_ELASTIC_ALIAS_DELETE,
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => 'alias'], 'emsco-core'),
            routeParameters: ['name' => 'name']
        )->setButtonType('outline-danger');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    #[\Override]
    public function getQueryName(): string
    {
        return 'EnvironmentUnreferencedAlias';
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
            $dataSource = new ArrayDataSource(\array_map(static fn (array $alias) => [
                'name' => $alias['name'],
                'total' => $alias['total'],
                'indexes' => $alias['indexes'],
                'countIndexes' => \count($alias['indexes']),
            ], $this->aliasService->getUnreferencedAliases()));
        }

        return $dataSource->search($searchValue);
    }
}

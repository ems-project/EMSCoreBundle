<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\Condition\Equals;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;

use function Symfony\Component\Translation\t;

class ContentTypeDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public const ACTION_UPDATE_MAPPING = 'action_update_mapping';
    public const ACTION_ACTIVATE = 'action_activate';
    public const ACTION_DEACTIVATE = 'action_deactivate';

    public function __construct(
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace,
    ) {
    }

    public function build(QueryTable $table): void
    {
        $table->setExtraFrontendOption(['pageLength' => 25]);

        $table->setDefaultOrder('orderKey')->setLabelAttribute('name');

        $table->addColumn(t('key.loop_count', [], 'emsco-core'), 'orderKey', 'loopCount');
        $columnName = $table->addColumn(t('field.name', [], 'emsco-core'), 'name', 'name');
        $columnName->setItemIconCallback(fn (array $data) => $data['icon'] ?? null);

        $table->addColumn(t('field.singular', [], 'emsco-core'), 'singularName', 'singularName');
        $table->addColumn(t('field.plural', [], 'emsco-core'), 'pluralName', 'pluralName');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.environment_default', [], 'emsco-core'),
            blockName: 'environmentName',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'environmentLabel'
        ));

        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.is_dirty', [], 'emsco-core'),
            attribute: 'dirty'
        ));
        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.is_active', [], 'emsco-core'),
            attribute: 'active'
        ));

        foreach (['loopCount', 'name', 'singularName', 'pluralName'] as $colorColumn) {
            $table->getColumnByName($colorColumn)?->setDataClassCallback(
                callback: fn (array $data) => \sprintf('text-%s', $data['color'])
            );
        }

        $this->addItemEdit($table, Routes::ADMIN_CONTENT_TYPE_EDIT);

        $table->addItemGetAction(
            route: Routes::ADMIN_CONTENT_TYPE_REORDER,
            labelKey: t('key.structure', [], 'emsco-core'),
            icon: 'sitemap'
        );
        $table->addItemGetAction(
            route: Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX,
            labelKey: t('key.actions', [], 'emsco-core'),
            icon: 'gear'
        );
        $table->addItemGetAction(
            route: Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX,
            labelKey: t('key.views', [], 'emsco-core'),
            icon: 'tv'
        );

        $table->addItemGetAction(
            route: Routes::ADMIN_CONTENT_TYPE_EXPORT,
            labelKey: t('action.export', [], 'emsco-core'),
            icon: 'sign-out'
        );

        $updateMapping = $table->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_REFRESH_MAPPING,
            labelKey: t('action.update_mapping', [], 'emsco-core'),
            icon: 'refresh',
            messageKey: t('type.confirm', ['type' => 'content_type_mapping'], 'emsco-core')
        );
        $updateMapping->setButtonType('primary');
        $updateMapping->addCondition(new Equals('[dirty]', true));

        $activateAction = $table->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_ACTIVATE,
            labelKey: t('action.activate', [], 'emsco-core'),
            icon: 'check',
            messageKey: t('type.confirm', ['type' => 'content_type_activate'], 'emsco-core')
        );
        $activateAction->setButtonType('primary');
        $activateAction->addCondition(new Equals('[active]', false));

        $deactivateAction = $table->addItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_DEACTIVATE,
            labelKey: t('action.deactivate', [], 'emsco-core'),
            icon: 'warning',
            messageKey: t('type.confirm', ['type' => 'content_type_deactivate'], 'emsco-core')
        );
        $deactivateAction->setButtonType('primary');
        $deactivateAction->addCondition(new Equals('[active]', true));

        $this
            ->addItemDelete($table, 'content_type', Routes::ADMIN_CONTENT_TYPE_REMOVE)
            ->addTableToolbarActionAdd($table, Routes::ADMIN_CONTENT_TYPE_ADD);

        $table->addToolbarAction(
            label: t('action.add_referenced', [], 'emsco-core'),
            icon: 'fa fa-plus',
            routeName: Routes::ADMIN_CONTENT_TYPE_ADD_REFERENCED_INDEX,
        )->setCssClass('btn btn-sm btn-primary');

        if ($this->contentTypeService->hasSearch(isActive: false)) {
            $table->addTableAction(
                name: self::ACTION_ACTIVATE,
                icon: 'fa fa-check',
                labelKey: t('type.selection', ['type' => 'content_type_activate'], 'emsco-core'),
                confirmationKey: t('type.confirm', ['type' => 'content_type_activate_all'], 'emsco-core')
            )->setCssClass('btn btn-sm btn-primary');
        }

        if ($this->contentTypeService->hasSearch(isActive: true)) {
            $table->addTableAction(
                name: self::ACTION_DEACTIVATE,
                icon: 'fa fa-warning',
                labelKey: t('type.selection', ['type' => 'content_type_deactivate'], 'emsco-core'),
                confirmationKey: t('type.confirm', ['type' => 'content_type_deactivate_all'], 'emsco-core')
            )->setCssClass('btn btn-sm btn-primary');
        }

        if ($this->contentTypeService->hasSearch(isDirty: true)) {
            $table->addTableAction(
                name: self::ACTION_UPDATE_MAPPING,
                icon: 'fa fa-refresh',
                labelKey: t('type.selection', ['type' => 'content_type_mapping'], 'emsco-core'),
                confirmationKey: t('type.confirm', ['type' => 'content_type_mapping_all'], 'emsco-core')
            )->setCssClass('btn btn-sm btn-primary');
        }

        $this->addTableActionDelete($table, 'content_type');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    public function getQueryName(): string
    {
        return 'contentType';
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->contentTypeRepository->makeQueryBuilder(searchValue: $searchValue);
        $qb
            ->select('c.id as id')
            ->addSelect('c.orderKey as orderKey')
            ->addSelect('c.icon as icon')
            ->addSelect('c.color as color')
            ->addSelect('c.name as name')
            ->addSelect('c.singularName as singularName')
            ->addSelect('c.pluralName as pluralName')
            ->addSelect('c.active as active')
            ->addSelect('c.dirty as dirty')
            ->addSelect('COALESCE(e.label, e.name) as environmentLabel')
            ->addSelect('e.color as environmentColor')
            ->setFirstResult($from)
            ->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy($orderField, $orderDirection);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->contentTypeRepository
            ->makeQueryBuilder(searchValue: $searchValue)
            ->select('count(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

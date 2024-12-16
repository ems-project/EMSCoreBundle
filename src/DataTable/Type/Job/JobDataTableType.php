<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Job;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\JobService;

use function Symfony\Component\Translation\t;

class JobDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;
    public const ACTION_DELETE_ALL = 'delete_all';

    public function __construct(
        JobService $jobService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($jobService);
    }

    public function build(EntityTable $table): void
    {
        $table
            ->setDefaultOrder('created', 'desc')
            ->setLabelAttribute('id');

        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_created', [], 'emsco-core'),
            attribute: 'created'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_modified', [], 'emsco-core'),
            attribute: 'modified'
        ));

        $table->addColumn(t('field.command', [], 'emsco-core'), 'command');
        $table->addColumn(t('field.tag', [], 'emsco-core'), 'tag');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.status', [], 'emsco-core'),
            blockName: 'jobStatus',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'progress'
        ));

        $table->addItemGetAction(
            route: 'emsco_job_status',
            labelKey: t('action.status', [], 'emsco-core'),
            icon: 'eye'
        );

        $this
            ->addItemDelete($table, 'job', 'job.delete')
            ->addTableToolbarActionAdd($table, 'job.add')
            ->addTableActionDelete($table, 'job');

        $table->addMassAction(
            name: self::ACTION_DELETE_ALL,
            label: t('action.delete_all', [], 'emsco-core'),
            icon: 'fa fa-eraser',
            confirmationKey: t('type.confirm', ['type' => 'delete_all_job'], 'emsco-core')
        );
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

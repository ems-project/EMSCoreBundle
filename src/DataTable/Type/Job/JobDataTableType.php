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
    public const ACTION_CLEAN = 'clean';

    public function __construct(
        JobService $jobService,
        private readonly string $templateNamespace
    ) {
        parent::__construct($jobService);
    }

    public function build(EntityTable $table): void
    {
        $table->setLabelAttribute('id');

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

        $table->addTableAction(
            name: self::ACTION_CLEAN,
            icon: 'fa fa-eraser',
            labelKey: t('action.clean', [], 'emsco-core'),
            confirmationKey: t('action.confirmation', [], 'emsco-core')
        )->setCssClass('btn btn-sm btn-default');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

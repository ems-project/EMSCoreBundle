<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;

class FormSubmissionDataTableType extends AbstractEntityTableType
{
    public function __construct(FormSubmissionService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn('form-submission.index.column.id', 'id');
        $table->addColumn('form-submission.index.column.instance', 'instance');
        $table->addColumn('form-submission.index.column.label', 'label');
        $table->addColumn('form-submission.index.column.form', 'name');
        $table->addColumn('form-submission.index.column.locale', 'locale');
        $table->addColumnDefinition(new DatetimeTableColumn('form-submission.index.column.created', 'created'));
        $table->addColumnDefinition(new DatetimeTableColumn('form-submission.index.column.expire_date', 'expireDate'));

        $table->addItemGetAction('form.submissions.download', 'form-submission.form-submissions.download', 'download');
        $table->addItemPostAction('form.submissions.process', 'form-submission.form-submissions.process', 'check', 'form-submission.form-submissions.confirm');

        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'form-submission.index.delete_selected', 'form-submission.form-submissions.delete_selected_confirm');
        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'form-submission.form-submissions.download_selected', 'form-submission.form-submissions.download_selected_confirm');
        $table->addTableAction(TableAbstract::EXPORT_ACTION, 'fa fa-file-excel-o', 'form-submission.form-submissions.export_selected', 'form-submission.form-submissions.export_selected_confirm');
        $table->setDefaultOrder('created', 'desc');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_FORM_CRM];
    }
}

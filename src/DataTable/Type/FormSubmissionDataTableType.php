<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;

use function Symfony\Component\Translation\t;

class FormSubmissionDataTableType extends AbstractEntityTableType
{
    public function __construct(FormSubmissionService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn(t('field.id', [], 'emsco-core'), 'id');
        $table->addColumn(t('field.instance', [], 'emsco-core'), 'instance');
        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        $table->addColumn(t('field.form', [], 'emsco-core'), 'name');
        $table->addColumn(t('field.locale', [], 'emsco-core'), 'locale');
        $table->addColumnDefinition(new DatetimeTableColumn(t('field.date_created', [], 'emsco-core'), 'created'));
        $table->addColumnDefinition(new DatetimeTableColumn(t('field.date_expiration', [], 'emsco-core'), 'expireDate'));

        $table->addItemGetAction(
            'form.submissions.download',
            labelKey: t('action.download', [], 'emsco-core'),
            icon: 'download',
            attributes: ['data-testid' => 'form-submission-download']
        );
        $table->addItemPostAction(
            route: 'form.submissions.process',
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'check',
            messageKey: t('action.confirmation', [], 'emsco-core'),
            attributes: ['data-testid' => 'form-submission-process']
        );

        $table->addTableAction(
            name: TableAbstract::DELETE_ACTION,
            icon: 'fa fa-trash',
            labelKey: t('action.delete_selected', [], 'emsco-core'),
            confirmationKey: t('action.confirmation', [], 'emsco-core'),
            attributes: ['data-testid' => 'form-submission-delete-all']
        );
        $table->addTableAction(
            name: TableAbstract::DOWNLOAD_ACTION,
            icon: 'fa fa-download',
            labelKey: t('action.download_selected', [], 'emsco-core'),
            attributes: ['data-testid' => 'form-submission-download-all']
        );
        $table->addTableAction(
            name: TableAbstract::EXPORT_ACTION,
            icon: 'fa fa-file-excel-o',
            labelKey: t('action.export_selected', [], 'emsco-core'),
            attributes: ['data-testid' => 'form-submission-export-all']
        );
        $table->setDefaultOrder('created', 'desc');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_FORM_CRM];
    }
}

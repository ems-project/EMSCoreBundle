<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Controller\UploadedFileController;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\FileService;

class UploadedFileLogDataTableType extends AbstractEntityTableType
{
    public function __construct(FileService $entityService)
    {
        parent::__construct($entityService);
    }

    public function build(EntityTable $table): void
    {
        $table->addColumnDefinition(new BoolTableColumn('uploaded-file.index.column.available', 'available'));
        $table->addColumn('uploaded-file.index.column.name', 'name')
            ->setRoute('ems_file_download', function (UploadedAsset $data) {
                if (!$data->getAvailable()) {
                    return null;
                }

                return [
                    'sha1' => $data->getSha1(),
                    'type' => $data->getType(),
                    'name' => $data->getName(),
                ];
            });
        $table->addColumnDefinition(new DatetimeTableColumn('uploaded-file.index.column.created', 'created'));
        $table->addColumnDefinition(new UserTableColumn('uploaded-file.index.column.username', 'user'));
        $table->addColumnDefinition(new BytesTableColumn('uploaded-file.index.column.size', 'size'));
        $table->addColumnDefinition(new BoolTableColumn('uploaded-file.index.column.hidden', 'hidden'));
        $table->addColumnDefinition(new DatetimeTableColumn('uploaded-file.index.column.head_last', 'headLast'));
        $table->addColumn('uploaded-file.index.column.type', 'type');
        $table->addColumn('uploaded-file.index.column.sha1', 'sha1');

        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'uploaded-file.uploaded-file.download_selected', 'uploaded-file.uploaded-file.download_selected_confirm');

        $table->addDynamicItemPostAction('ems_core_uploaded_file_show_hide', 'uploaded-file.action.hide-show', 'eye', 'uploaded-file.hide-show-confirm', ['assetId' => 'id']);
        $table->addTableAction(UploadedFileController::HIDE_ACTION, 'fa fa-eye', 'uploaded-file.uploaded-file.hide-show', 'uploaded-file.uploaded-file.hide-show_confirm');

        $itemDeleteAction = $table->addDynamicItemPostAction('ems_file_soft_delete', 'uploaded-file.action.soft-delete', 'trash', 'uploaded-file.soft-delete-confirm', ['id' => 'id']);
        $itemDeleteAction->setButtonType('outline-danger');
        $deleteAction = $table->addTableAction(UploadedFileController::SOFT_DELETE_ACTION, 'fa fa-trash', 'uploaded-file.uploaded-file.soft_delete_selected', 'uploaded-file.uploaded-file.soft_delete_selected_confirm');
        $deleteAction->setCssClass('btn btn-outline-danger');

        $table->setDefaultOrder('created', 'desc');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

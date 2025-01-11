<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\UploadedAsset;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\FileService;

use function Symfony\Component\Translation\t;

class UploadedAssetAdminDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public const TOGGLE_VISIBILITY_ACTION = 'action_toggle_visibility';

    public function __construct(FileService $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('created', 'desc');

        $columnName = $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $columnName->setItemIconCallback(fn (UploadedAsset $data) => Encoder::getFontAwesomeFromMimeType($data->getType(), EMSCoreBundle::FONTAWESOME_VERSION));
        $columnName->setRoute('ems_file_download', function (UploadedAsset $data) {
            if (!$data->getAvailable()) {
                return null;
            }

            return [
                'sha1' => $data->getSha1(),
                'type' => $data->getType(),
                'name' => $data->getName(),
            ];
        });

        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.available', [], 'emsco-core'),
            attribute: 'available'
        ));
        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.hidden', [], 'emsco-core'),
            attribute: 'hidden'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.last_seen', [], 'emsco-core'),
            attribute: 'headLast'
        ));
        $table->addColumnDefinition(new UserTableColumn(
            titleKey: t('field.user_uploaded', [], 'emsco-core'),
            attribute: 'user'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_upload', [], 'emsco-core'),
            attribute: 'created'
        ));
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_modified', [], 'emsco-core'),
            attribute: 'modified'
        ));
        $table->addColumnDefinition(new BytesTableColumn(
            titleKey: t('field.file.size', [], 'emsco-core'),
            attribute: 'size'
        ))->setCellClass('text-right');
        $table->addColumn(
            titleKey: t('field.hash', [], 'emsco-core'),
            attribute: 'sha1'
        );

        $table->addDynamicItemPostAction(
            route: Routes::UPLOAD_ASSET_ADMIN_TOGGLE_VISIBILITY,
            labelKey: t('action.toggle_visibility', [], 'emsco-core'),
            icon: 'eye',
            routeParameters: ['assetId' => 'id']
        );

        $this->addItemDelete($table, 'uploaded_file', Routes::UPLOAD_ASSET_ADMIN_DELETE);

        $table->addTableAction(
            name: TableAbstract::DOWNLOAD_ACTION,
            icon: 'fa fa-download',
            labelKey: t('action.download_selected', [], 'emsco-core')
        )->setCssClass('btn btn-sm btn-default');

        $table->addTableAction(
            name: self::TOGGLE_VISIBILITY_ACTION,
            icon: 'fa fa-eye',
            labelKey: t('action.toggle_visibility_selected', [], 'emsco-core'),
        )->setCssClass('btn btn-sm btn-default');

        $this->addTableActionDelete($table, 'uploaded_file');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

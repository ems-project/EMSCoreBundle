<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Routing\RouterInterface;

class WysiwygUploadedFileDataTableType extends AbstractEntityTableType
{
    public function __construct(
        FileService $fileService,
        private readonly RouterInterface $router
    ) {
        parent::__construct($fileService);
    }

    public function build(EntityTable $table): void
    {
        $router = $this->router;

        $table->addColumn('uploaded-file.index.column.name', 'name')
            ->addHtmlAttribute('data-url', fn (UploadedAsset $data) => EMSLink::EMSLINK_ASSET_PREFIX.$data->getSha1().'?name='.$data->getName().'&type='.$data->getType())
            ->addHtmlAttribute('data-json', function (UploadedAsset $data) use ($router) {
                $json = $data->getData();
                $json = \array_merge($json, [
                    'preview_url' => $router->generate('ems_asset_processor', [
                        'hash' => $data->getSha1(),
                        'processor' => 'preview',
                        'type' => $data->getType(),
                        'name' => $data->getName(),
                    ]),
                    'view_url' => $router->generate('ems.file.view', [
                        'sha1' => $data->getSha1(),
                        'type' => $data->getType(),
                        'name' => $data->getName(),
                    ]),
                ]);

                return Json::encode($json);
            })
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
        $table->addColumn('uploaded-file.index.column.type', 'type')->setItemIconCallback(fn (UploadedAsset $data) => Encoder::getFontAwesomeFromMimeType($data->getType(), EMSCoreBundle::FONTAWESOME_VERSION));

        $table->setDefaultOrder('created', 'desc');
    }

    /**
     * @return array{'available': false}
     */
    public function getContext(array $options): array
    {
        return ['available' => false];
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_USER];
    }
}

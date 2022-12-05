<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Common\Standard\Json;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class UploadedFileWysiwygController extends AbstractController
{
    public function __construct(private readonly FileService $fileService, private readonly AjaxService $ajax, private readonly RouterInterface $router)
    {
    }

    public function ajaxDataTable(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initTable();

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render('@EMSCore/uploaded-file-wysiwyg/index.html.twig', [
            'form' => $form->createView(),
            'CKEditorFuncNum' => $request->query->get('CKEditorFuncNum') ?: 0,
        ]);
    }

    public function modal(): Response
    {
        $form = $this->createForm(TableType::class, $this->initTable());

        return $this->ajax->newAjaxModel('@EMSCore/uploaded-file-wysiwyg/modal.html.twig')
            ->setBody('modalBody', [
                'form' => $form->createView(),
            ])
            ->getResponse();
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->fileService, $this->router->generate('ems_core_uploaded_file_wysiwyg_ajax'), ['available' => false]);
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

        return $table;
    }
}

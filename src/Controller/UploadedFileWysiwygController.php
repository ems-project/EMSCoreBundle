<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UploadedFileWysiwygController extends AbstractController
{
    private LoggerInterface $logger;
    private FileService $fileService;

    public function __construct(LoggerInterface $logger, FileService $fileService)
    {
        $this->logger = $logger;
        $this->fileService = $fileService;
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

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->fileService, $this->generateUrl('ems_core_uploaded_file_wysiwyg_ajax'), ['available' => false]);
        $table->addColumn('uploaded-file.index.column.name', 'name')
            ->addHtmlAttribute('data-url', function (UploadedAsset $data) {
                return EMSLink::EMSLINK_ASSET_PREFIX.$data->getSha1().'?name='.$data->getName().'&type='.$data->getType();
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
        $table->addColumn('uploaded-file.index.column.type', 'type')->setItemIconCallback(function (UploadedAsset $data) {
            return Encoder::getFontAwesomeFromMimeType($data->getType(), EMSCoreBundle::FONTAWESOME_VERSION);
        });

        $table->setDefaultOrder('created', 'desc');

        return $table;
    }
}

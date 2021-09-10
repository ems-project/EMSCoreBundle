<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\UploadedFileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadedFileController extends AbstractController
{
    /** @var string */
    public const SOFT_DELETE_ACTION = 'soft_delete';
    /** @var string */
    public const HIDE_ACTION = 'hide';

    private LoggerInterface $logger;
    private FileService $fileService;
    private UploadedFileService $uploadedFileService;

    public function __construct(LoggerInterface $logger, FileService $fileService, UploadedFileService $uploadedFileService)
    {
        $this->logger = $logger;
        $this->fileService = $fileService;
        $this->uploadedFileService = $uploadedFileService;
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
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
                        if (!$this->isGranted('ROLE_ADMIN')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }

                        return $this->downloadMultiple($table->getSelected());
                    case self::SOFT_DELETE_ACTION:
                        if (!$this->isGranted('ROLE_ADMIN')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }
                        $this->fileService->removeSingleFileEntity($table->getSelected());
                        break;
                }
            } else {
                $this->logger->error('log.controller.uploaded-file.unknown_action');
            }

            return $this->redirectToRoute('ems_core_uploaded_file_index');
        }

        return $this->render('@EMSCore/uploaded-file/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function logs(Request $request): Response
    {
        $table = $this->initTable();

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
                        if (!$this->isGranted('ROLE_ADMIN')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }

                        return $this->downloadMultiple($table->getSelected());
                    case self::HIDE_ACTION:
                        if (!$this->isGranted('ROLE_PUBLISHER')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }
                        $this->fileService->toggleFileEntitiesVisibility($table->getSelected());
                        break;
                    case self::SOFT_DELETE_ACTION:
                        if (!$this->isGranted('ROLE_ADMIN')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }
                        $this->fileService->removeSingleFileEntity($table->getSelected());
                        break;
                }
            } else {
                $this->logger->error('log.controller.uploaded-file-logs.unknown_action');
            }

            return $this->redirectToRoute('ems_core_uploaded_file_logs');
        }

        return $this->render('@EMSCore/uploaded-file-logs/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function showHide(Request $request, string $assetId): Response
    {
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            throw new AccessDeniedException($request->getPathInfo());
        }
        $this->fileService->toggleFileEntitiesVisibility([$assetId]);

        return $this->redirectToRoute('ems_core_uploaded_file_logs');
    }

    /**
     * @param array<string> $fileIds
     */
    private function downloadMultiple(array $fileIds): Response
    {
        try {
            $response = $this->fileService->createDownloadForMultiple($fileIds);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return $this->redirectToRoute('ems_core_uploaded_file_index');
        }

        return $response;
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->uploadedFileService, $this->generateUrl('ems_core_uploaded_file_logs_ajax'));
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

        if ($this->isGranted('ROLE_PUBLISHER')) {
            $table->addDynamicItemPostAction('ems_core_uploaded_file_show_hide', 'uploaded-file.action.hide-show', 'eye', 'uploaded-file.hide-show-confirm', ['assetId' => 'id']);
            $table->addTableAction(self::HIDE_ACTION, 'fa fa-eye', 'uploaded-file.uploaded-file.hide-show', 'uploaded-file.uploaded-file.hide-show_confirm');
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            $itemDeleteAction = $table->addDynamicItemPostAction('ems_file_soft_delete', 'uploaded-file.action.soft-delete', 'trash', 'uploaded-file.soft-delete-confirm', ['id' => 'id']);
            $itemDeleteAction->setButtonType('outline-danger');
            $deleteAction = $table->addTableAction(self::SOFT_DELETE_ACTION, 'fa fa-trash', 'uploaded-file.uploaded-file.soft_delete_selected', 'uploaded-file.uploaded-file.soft_delete_selected_confirm');
            $deleteAction->setCssClass('btn btn-outline-danger');
        }

        $table->setDefaultOrder('created', 'desc');

        return $table;
    }
}

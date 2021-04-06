<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadedFileController extends AbstractController
{
    /** @var string */
    public const SOFT_DELETE_ACTION = 'soft_delete';
    /** @var string */
    public const HARD_DELETE_ACTION = 'hard_delete';

    private LoggerInterface $logger;
    private FileService $fileService;

    public function __construct(LoggerInterface $logger, FileService $fileService)
    {
        $this->logger = $logger;
        $this->fileService = $fileService;
    }

    public function index(Request $request): Response
    {
        $table = new EntityTable($this->fileService);
        $column = $table->addColumn('uploaded-file.index.column.name', 'name');
        $column->setRoutePath('ems_file_download', function (UploadedAsset $data) {
            return [
                'sha1' => $data->getSha1(),
                'type' => $data->getType(),
                'name' => $data->getName(),
            ];
        });
        $column->setRouteTarget('_blank');
        $table->addColumn('uploaded-file.index.column.sha1', 'sha1');
        $table->addColumn('uploaded-file.index.column.type', 'type');
        $table->addColumn('uploaded-file.index.column.username', 'user');
        $tableColumn = $table->addColumn('uploaded-file.index.column.created', 'created');
        $tableColumn->setDateTimeProperty(true);
        $tableColumn = $table->addColumn('uploaded-file.index.column.size', 'size');
        $tableColumn->setFormatBytes(true);

        $table->addDynamicItemPostAction('ems_file_soft_delete', 'uploaded-file.action.soft-delete', 'minus-square', 'uploaded-file.soft-delete-confirm', ['id' => 'id']);
        $table->addDynamicItemPostAction('ems_file_hard_delete', 'uploaded-file.action.hard-delete', 'trash', 'uploaded-file.hard-delete-confirm', ['hash' => 'sha1']);

        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'uploaded-file.uploaded-file.download_selected', 'uploaded-file.uploaded-file.download_selected_confirm');
        $table->addTableAction(self::SOFT_DELETE_ACTION, 'fa fa-minus-square', 'uploaded-file.uploaded-file.soft_delete_selected', 'uploaded-file.uploaded-file.soft_delete_selected_confirm');
        $table->addTableAction(self::HARD_DELETE_ACTION, 'fa fa-trash', 'uploaded-file.uploaded-file.hard_delete_selected', 'uploaded-file.uploaded-file.hard_delete_selected_confirm');

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
                        return $this->downloadMultiple($table->getSelected());
                    case self::SOFT_DELETE_ACTION:
                        $this->fileService->removeSingleFileEntity($table->getSelected());
                        break;
                    case self::HARD_DELETE_ACTION:
                        $this->fileService->hardRemoveFiles($table->getSelected());
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
}

<?php

namespace EMS\CoreBundle\Controller;

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
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadedFileController extends AbstractController
{
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
        $tableColumn = $table->addColumn('uploaded-file.index.column.created', 'created');
        $tableColumn->setDateTimeProperty(true);
        $table->addColumn('uploaded-file.index.column.name', 'name');
        $table->addColumn('uploaded-file.index.column.sha1', 'sha1');
        $table->addColumn('uploaded-file.index.column.type', 'type');
        $table->addColumn('uploaded-file.index.column.username', 'user');
        $tableColumn = $table->addColumn('uploaded-file.index.column.size', 'size');
        $tableColumn->setFormatBytes(true);

        $table->addDynamicItemGetAction('ems_file_download', 'uploaded-file.action.download', 'download', ['sha1' => 'sha1', 'name' => 'name']);
        $table->addDynamicItemPostAction('ems_file_remove', 'uploaded-file.action.remove', 'delete', 'uploaded-file.delete-confirm', ['sha1' => 'sha1']);

        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'uploaded-file.uploaded-file.download_selected', 'uploaded-file.uploaded-file.download_selected_confirm');

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
                        return $this->downloadMultiple($table->getSelected());
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
     *
     * @return StreamedResponse|Response
     */
    private function downloadMultiple(array $fileIds)
    {
        try {
            $response = $this->fileService->createDownloadForMultiple($fileIds);
        } catch (\Exception $e) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('ems_core_uploaded_file_index');
        }

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'files.zip')
        );

        return $response;
    }
}

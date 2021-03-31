<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Form\Data\EntityTable;
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
        $table->addColumn('uploaded-file.index.column.size', 'size');

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    // Todo: form actions
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
}

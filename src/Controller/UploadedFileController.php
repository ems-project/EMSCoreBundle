<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\UploadedFileLogDataTableType;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TranslationTableColumn;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\FileService;
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
    final public const SOFT_DELETE_ACTION = 'soft_delete';
    /** @var string */
    final public const HIDE_ACTION = 'hide';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FileService $fileService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function ajaxDataTableGroupedByHash(Request $request): Response
    {
        $table = $this->initFileTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render("@$this->templateNamespace/datatable/ajax.html.twig", [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function index(Request $request): Response
    {
        $table = $this->initFileTable();

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
                        $ids = $this->fileService->hashesToIds($table->getSelected());

                        return $this->downloadMultiple($ids);
                    case self::HIDE_ACTION:
                        if (!$this->isGranted('ROLE_PUBLISHER')) {
                            throw new AccessDeniedException($request->getPathInfo());
                        }
                        $this->fileService->hideByHashes($table->getSelected());
                }
            } else {
                $this->logger->error('log.controller.uploaded-file.unknown_action');
            }

            return $this->redirectToRoute('ems_core_uploaded_file_index');
        }

        return $this->render("@$this->templateNamespace/uploaded-file/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function logs(Request $request): Response
    {
        $table = $this->dataTableFactory->create(UploadedFileLogDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DOWNLOAD_ACTION:
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

        return $this->render("@$this->templateNamespace/uploaded-file-logs/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function hideByHash(Request $request, string $hash): Response
    {
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            throw new AccessDeniedException($request->getPathInfo());
        }
        $this->fileService->hideByHashes([$hash]);

        return $this->redirectToRoute('ems_core_uploaded_file_index');
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

    private function initFileTable(): QueryTable
    {
        $table = new QueryTable($this->templateNamespace, $this->fileService, 'uploaded-files-grouped-by-hash', $this->generateUrl('ems_core_uploaded_file_ajax'));
        $table->addColumn('uploaded-file.index.column.name', 'name')
            ->setRoute('ems_file_download', function (array $data) {
                if (!\is_string($data['id'] ?? null) || !\is_string($data['type'] ?? null) || !\is_string($data['name'] ?? null)) {
                    return null;
                }

                return [
                    'sha1' => $data['id'],
                    'type' => $data['type'],
                    'name' => $data['name'],
                ];
            });
        $table->addColumnDefinition(new BytesTableColumn('uploaded-file.index.column.size', 'size'))->setCellClass('text-right');
        $table->addColumnDefinition(new TranslationTableColumn('uploaded-file.index.column.kind', 'type', 'emsco-mimetypes'));
        $table->addColumnDefinition(new DatetimeTableColumn('uploaded-file.index.column.date-added', 'created'));
        $table->addColumnDefinition(new DatetimeTableColumn('uploaded-file.index.column.date-modified', 'modified'));
        $table->setDefaultOrder('name', 'asc');

        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'uploaded-file.uploaded-file.download_selected', 'uploaded-file.uploaded-file.download_selected_confirm');
        if ($this->isGranted('ROLE_PUBLISHER')) {
            $table->addDynamicItemPostAction('ems_core_uploaded_file_hide_by_hash', 'uploaded-file.action.delete', 'trash', 'uploaded-file.delete-confirm', ['hash' => 'id'])
                ->setButtonType('outline-danger');
            $table->addTableAction(self::HIDE_ACTION, 'fa fa-trash', 'uploaded-file.delete-all', 'uploaded-file.uploaded-file.delete-all_confirm')
                ->setCssClass('btn btn-outline-danger');
        }

        return $table;
    }
}

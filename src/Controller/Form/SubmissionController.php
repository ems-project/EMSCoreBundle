<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Form;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Form\Data\DateTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\User\UserInterface;

final class SubmissionController extends AbstractController
{
    public function __construct(private readonly FormSubmissionService $formSubmissionService, private readonly LoggerInterface $logger, private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService)
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

    public function indexAction(Request $request, UserInterface $user): Response
    {
        $table = $this->initTable();
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DELETE_ACTION:
                        $this->formSubmissionService->processByIds($table->getSelected(), $user);
                        break;
                    case TableAbstract::DOWNLOAD_ACTION:
                        return $this->downloadMultiple($table->getSelected());
                    case TableAbstract::EXPORT_ACTION:
                        $config = $config = $this->formSubmissionService->generateExportConfig($table->getSelected());

                        return $this->spreadsheetGeneratorService->generateSpreadsheet($config);
                    default:
                        $this->logger->error('log.controller.action.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.action.unknown_action');
            }

            return $this->redirectToRoute('form.submissions');
        }

        return $this->render('@EMSCore/form-submission/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function process(Request $request, UserInterface $user): RedirectResponse
    {
        $this->formSubmissionService->process($request->get('formSubmission'), $user);

        return $this->redirectToRoute('form.submissions');
    }

    public function download(string $formSubmission): Response
    {
        try {
            $response = $this->formSubmissionService->createDownload($formSubmission);

            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                \sprintf('%s.zip', $formSubmission)
            ));

            return $response;
        } catch (\Exception) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('form.submissions');
        }
    }

    /**
     * @param array<string> $submissionIds
     */
    private function downloadMultiple(array $submissionIds): Response
    {
        try {
            $response = $this->formSubmissionService->createDownloadForMultiple($submissionIds);
        } catch (\Exception) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('form.submissions');
        }

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'submissions.zip')
        );

        return $response;
    }

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->formSubmissionService, $this->generateUrl('ems_core_submission_ajax'));
        $table->addColumn('form-submission.index.column.id', 'id');
        $table->addColumn('form-submission.index.column.instance', 'instance');
        $table->addColumn('form-submission.index.column.label', 'label');
        $table->addColumn('form-submission.index.column.form', 'name');
        $table->addColumn('form-submission.index.column.locale', 'locale');
        $table->addColumnDefinition(new DatetimeTableColumn('form-submission.index.column.created', 'created'));
        $table->addColumnDefinition(new DateTableColumn('form-submission.index.column.expire_date', 'expireDate'));

        $table->addItemGetAction('form.submissions.download', 'form-submission.form-submissions.download', 'download');
        $table->addItemPostAction('form.submissions.process', 'form-submission.form-submissions.process', 'check', 'form-submission.form-submissions.confirm');

        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'action.actions.delete_selected', 'form-submission.form-submissions.delete_selected_confirm');
        $table->addTableAction(TableAbstract::DOWNLOAD_ACTION, 'fa fa-download', 'form-submission.form-submissions.download_selected', 'form-submission.form-submissions.download_selected_confirm');
        $table->addTableAction(TableAbstract::EXPORT_ACTION, 'fa fa-file-excel-o', 'form-submission.form-submissions.export_selected', 'form-submission.form-submissions.export_selected_confirm');
        $table->setDefaultOrder('created', 'desc');

        return $table;
    }
}

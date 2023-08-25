<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskHandleType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\TemplateWrapper;

final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly AjaxService $ajax,
        private readonly FormFactoryInterface $formFactory,
        private readonly TableExporter $tableExporter,
        private readonly string $coreDateFormat,
        private readonly string $templateNamespace
    ) {
    }

    public function ajaxDataTable(Request $request, string $tab): Response
    {
        $table = $this->getTable($request, $tab);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render("@$this->templateNamespace/datatable/ajax.html.twig", [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function ajaxDataTableCSV(Request $request, string $tab): Response
    {
        $table = $this->getTable($request, $tab, true);
        $table->setExportFileName('tasks');

        return $this->tableExporter->exportCSV($table);
    }

    public function ajaxDataTableExcel(Request $request, string $tab): Response
    {
        $table = $this->getTable($request, $tab, true);
        $table->setExportFileName('tasks');

        return $this->tableExporter->exportExcel($table);
    }

    public function ajaxGetTasks(Request $request, UserInterface $user, int $revisionId): Response
    {
        $revision = $this->taskManager->getRevision($revisionId);
        $ajaxTemplate = $this->getAjaxTemplate();

        if ($revision->hasTaskCurrent()) {
            $handle = $request->get('handle');
            $formHandle = $this->createForm(RevisionTaskHandleType::class, [], [
                'task' => $revision->getTaskCurrent(),
                'user' => $user,
                'handle' => $handle,
            ]);
            $formHandle->handleRequest($request);

            if ($formHandle->isSubmitted() && $formHandle->isValid()) {
                try {
                    $comment = $formHandle->getData()['comment'];
                    match ($handle) {
                        'send' => $this->taskManager->taskValidateRequest($revision->getTaskCurrent(), $revisionId, $comment),
                        'approve' => $this->taskManager->taskValidate($revision, true, $comment),
                        'reject' => $this->taskManager->taskValidate($revision, false, $comment),
                    };

                    return new JsonResponse(['success' => true]);
                } catch (\Throwable $e) {
                    return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return new JsonResponse([
            'tab' => $ajaxTemplate->renderBlock('tasksTab', \array_filter([
                'formHandle' => isset($formHandle) ? $formHandle->createView() : null,
                'revision' => $revision,
                'tasksPlanned' => $this->taskManager->getTasksPlanned($revisionId),
                'tasksApproved' => $this->taskManager->getTasksApproved($revisionId),
            ])),
        ]);
    }

    public function ajaxModalTask(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        $revision = $this->taskManager->getRevision($revisionId);

        return $this->getAjaxModal()
            ->setFooter('modalFooterActions', [
                'ouuid' => $revision->getOuuid(),
                'revisionId' => $revision->getId(),
                'contentType' => $revision->getContentType(),
                'isManager' => $this->taskManager->isTaskManager(),
                'fromRevision' => $request->query->getBoolean('fromRevision'),
            ])
            ->setBody('modalTaskBody', ['task' => $this->taskManager->getTask($taskId)])
            ->getResponse();
    }

    public function ajaxModalCreate(Request $request, int $revisionId): JsonResponse
    {
        $taskDTO = new TaskDTO();
        $ajaxModal = $this->getAjaxModal();

        $form = $this->createForm(RevisionTaskType::class, $taskDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $task = $this->taskManager->taskCreate($taskDTO, $revisionId);

                return $ajaxModal
                    ->addMessageSuccess('task.create.success', ['%title%' => $task->getTitle()])
                    ->setBodyHtml('')
                    ->setFooter('modalFooterClose')
                    ->getResponse();
            } catch (\Throwable) {
                $ajaxModal->addMessageError('task.error.ajax');
            }
        }

        return $ajaxModal
            ->setBody('modalCreateBody', ['form' => $form->createView()])
            ->setFooter('modalCreateFooter')
            ->getResponse();
    }

    public function ajaxModalUpdate(Request $request, UserInterface $user, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        if (!$task->isRequester($user) && !$this->isGranted('ROLE_TASK_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $taskDTO = TaskDTO::fromEntity($task, $this->coreDateFormat);

        $ajaxModal = $this->getAjaxModal();
        $ajaxModal->setFooter('modalUpdateFooter', ['task' => $task, 'revisionId' => $revisionId]);

        $form = $this->createForm(RevisionTaskType::class, $taskDTO, ['task_status' => $task->getStatus()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->taskUpdate($task, $taskDTO, $revisionId);

                return $ajaxModal
                    ->setTitle('task.update.title', ['%title%' => $task->getTitle()])
                    ->addMessageSuccess('task.update.success', ['%title%' => $task->getTitle()])
                    ->setBody('modalTaskBody', ['form' => $form->createView(), 'task' => $task])
                    ->getResponse();
            } catch (\Throwable) {
                $ajaxModal->addMessageError('task.error.ajax');
            }
        }

        return $ajaxModal
            ->setBody('modalTaskBody', ['form' => $form->createView(), 'task' => $task])
            ->getResponse();
    }

    public function ajaxModalDelete(Request $request, UserInterface $user, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        if (!$task->isRequester($user) && !$this->isGranted('ROLE_TASK_MANAGER')) {
            throw $this->createAccessDeniedException();
        }

        $ajaxModal = $this->getAjaxModal()->setTitle('task.delete.title', ['%title%' => $task->getTitle()]);

        $form = $this->formFactory->create(FormType::class);
        $form->add('comment', TextareaType::class, [
            'attr' => ['rows' => 4],
            'constraints' => new NotBlank(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->taskDelete($task, $revisionId, $form->getData()['comment']);

                return $ajaxModal
                    ->addMessageSuccess('task.delete.success', ['%title%' => $task->getTitle()])
                    ->setBodyHtml('')
                    ->setFooter('modalFooterClose')
                    ->getResponse();
            } catch (\Throwable) {
                $ajaxModal->addMessageError('task.error.ajax');
            }
        }

        return $ajaxModal
            ->setBody('modalDeleteBody', ['form' => $form->createView(), 'task' => $task])
            ->setFooter('modalDeleteFooter')
            ->getResponse();
    }

    public function ajaxReorder(Request $request, int $revisionId): JsonResponse
    {
        try {
            $data = Json::decode($request->getContent());
            $taskIds = $data['taskIds'] ?? [];

            $this->taskManager->tasksReorder($revisionId, $taskIds);

            return new JsonResponse([], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel("@$this->templateNamespace/revision/task/ajax.twig");
    }

    private function getAjaxTemplate(): TemplateWrapper
    {
        return $this->ajax->getTemplating()->load("@$this->templateNamespace/revision/task/ajax.twig");
    }

    private function getTable(Request $request, string $tab, bool $export = false): EntityTable
    {
        $filters = new TaskTableFilters();
        $formFilters = $this->formFactory->create(RevisionTaskFiltersType::class, $filters, ['tab' => $tab]);
        $formFilters->handleRequest($request);

        $tableUrl = $this->generateUrl('ems_core_task_ajax_datatable', ['tab' => $tab]);

        return $this->taskManager->getTable($tableUrl, $tab, $filters, $export);
    }
}

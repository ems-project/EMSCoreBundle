<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\TemplateWrapper;

final class TaskController extends AbstractController
{
    public function __construct(private readonly TaskManager $taskManager, private readonly AjaxService $ajax, private readonly FormFactoryInterface $formFactory, private readonly TableExporter $tableExporter)
    {
    }

    public function ajaxDataTable(Request $request, string $tab): Response
    {
        $table = $this->getTable($request, $tab);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
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

    public function ajaxGetTask(Request $request, int $revisionId): JsonResponse
    {
        $currentTask = $this->taskManager->getTaskCurrent($revisionId);
        $ajaxTemplate = $this->getAjaxTemplate();

        if ($currentTask && $currentTask->isOpen() && $this->taskManager->isTaskAssignee($currentTask)) {
            $form = $this->createCommentForm('validation-request', true);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $data = $form->getData();
                    $this->taskManager->taskValidateRequest($currentTask, $revisionId, $data['comment']);

                    return new JsonResponse([
                        'html' => $ajaxTemplate->renderBlock('currentTask', [
                            'task' => $currentTask,
                            'revisionId' => $revisionId,
                        ]),
                    ]);
                } catch (\Throwable $e) {
                    return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return new JsonResponse([
            'html' => $ajaxTemplate->renderBlock('currentTask', [
                'task' => $currentTask,
                'revisionId' => $revisionId,
                'formRequestValidation' => isset($form) ? $form->createView() : null,
            ]),
        ]);
    }

    public function ajaxGetTasks(Request $request, int $revisionId): Response
    {
        $tasks = $this->taskManager->getTasks($revisionId);
        $revision = $tasks->getRevision();
        $ajaxTemplate = $this->getAjaxTemplate();

        if ($revision->hasTaskCurrent() && Task::STATUS_PROGRESS !== $revision->getTaskCurrent()->getStatus()) {
            $action = $request->get('action');
            $formValidation = $this->createCommentForm('validation', 'approve' !== $action);
            $formValidation->handleRequest($request);

            if ($formValidation->isSubmitted() && $formValidation->isValid()) {
                try {
                    $comment = $formValidation->getData()['comment'];
                    $this->taskManager->taskValidate($revision, 'approve' === $action, $comment);

                    return $this->redirectToRoute('ems_core_task_ajax_tasks', ['revisionId' => $revisionId]);
                } catch (\Throwable $e) {
                    return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        $tasksList = [];
        foreach ($tasks as $task) {
            $taskItemContext = ['task' => $task, 'revision' => $revision, 'isCurrent' => $revision->isTaskCurrent($task)];
            if ($revision->isTaskCurrent($task) && isset($formValidation)) {
                $taskItemContext['formValidation'] = $formValidation->createView();
            }
            $tasksList[] = ['html' => $ajaxTemplate->renderBlock('taskItem', $taskItemContext)];
        }

        return new JsonResponse([
            'revision' => $revision,
            'tasks' => $tasksList,
            'tasks_approved_link' => $ajaxTemplate->renderBlock('tasksApprovedLink', [
                'count' => $this->taskManager->countApprovedTasks($revision),
            ]),
        ]);
    }

    public function ajaxGetTasksApproved(int $revisionId): JsonResponse
    {
        $tasks = $this->taskManager->getTasksApproved($revisionId);
        $revision = $tasks->getRevision();
        $ajaxTemplate = $this->getAjaxTemplate();

        $tasksList = [];
        foreach ($tasks as $task) {
            $tasksList[] = ['html' => $ajaxTemplate->renderBlock(
                'taskItemApproved',
                ['task' => $task, 'revision' => $revision]
            )];
        }

        return new JsonResponse(['tasks' => $tasksList]);
    }

    public function ajaxModalTask(int $revisionId, string $taskId): JsonResponse
    {
        $revision = $this->taskManager->getRevision($revisionId);

        return $this->getAjaxModal()
            ->setFooter('modalFooterActions', [
                'ouuid' => $revision->getOuuid(),
                'revisionId' => $revision->getId(),
                'contentType' => $revision->getContentType(),
                'isManager' => $this->taskManager->isTaskManager(),
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

    public function ajaxModalUpdate(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $taskDTO = TaskDTO::fromEntity($task);

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

    public function ajaxModalHistory(string $taskId): JsonResponse
    {
        return $this->getAjaxModal()
            ->setFooter('modalFooterClose')
            ->setBody('modalHistoryBody', ['task' => $this->taskManager->getTask($taskId)])
            ->getResponse();
    }

    public function ajaxDelete(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $ajaxModal = $this->getAjaxModal()
            ->setTitle('task.delete.title', ['%title%' => $task->getTitle()])
            ->setBodyHtml('')
            ->setFooter('modalFooterClose');

        try {
            $taskDTO = TaskDTO::fromEntity($task);
            $form = $this->createForm(RevisionTaskType::class, $taskDTO, ['task_status' => $task->getStatus()]);
            $form->handleRequest($request);

            $this->taskManager->taskDelete($task, $revisionId, $taskDTO->description);
            $ajaxModal->addMessageSuccess('task.delete.success', ['%title%' => $task->getTitle()]);
        } catch (\Throwable) {
            $ajaxModal->addMessageError('task.error.ajax');
        }

        return $ajaxModal->getResponse();
    }

    public function ajaxReorder(Request $request, int $revisionId): JsonResponse
    {
        $data = Json::decode($request->getContent());
        $taskIds = $data['taskIds'] ?? [];

        try {
            $this->taskManager->tasksReorder($revisionId, $taskIds);

            return new JsonResponse([], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @return FormInterface<FormInterface>
     */
    private function createCommentForm(string $name, bool $required): FormInterface
    {
        $form = $this->formFactory->createNamed($name);
        $form->add('comment', TextareaType::class, [
            'attr' => ['rows' => 4],
            'constraints' => $required ? [new NotBlank()] : [],
        ]);

        return $form;
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel('@EMSCore/revision/task/ajax.twig');
    }

    private function getAjaxTemplate(): TemplateWrapper
    {
        return $this->ajax->getTemplating()->load('@EMSCore/revision/task/ajax.twig');
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

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Core\DataTable\TableExporter;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Field\SelectUserPropertyType;
use EMS\CoreBundle\Form\Form\RevisionTaskType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
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
    private TaskManager $taskManager;
    private AjaxService $ajax;
    private FormFactoryInterface $formFactory;
    private TableExporter $tableExporter;
    private LoggerInterface $logger;

    public function __construct(
        TaskManager $taskManager,
        AjaxService $ajax,
        FormFactoryInterface $formFactory,
        TableExporter $tableExporter,
        LoggerInterface $logger
    ) {
        $this->taskManager = $taskManager;
        $this->ajax = $ajax;
        $this->formFactory = $formFactory;
        $this->tableExporter = $tableExporter;
        $this->logger = $logger;
    }

    public function dashboard(Request $request, string $tab): Response
    {
        $table = $this->getTable($tab);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render('@EMSCore/revision/task/dashboard.html.twig', [
            'table' => $table,
            'formTable' => $form->createView(),
            'currentTab' => $tab,
            'tabs' => $this->taskManager->getDashboardTabs(),
        ]);
    }

    public function ajaxDataTable(Request $request, string $tab): Response
    {
        $table = $this->getTable($tab);
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function ajaxDataTableCSV(string $tab): Response
    {
        $table = $this->getTable($tab, true);
        $table->setExportFileName('tasks');

        return $this->tableExporter->exportCSV($table);
    }

    public function ajaxDataTableExcel(string $tab): Response
    {
        $table = $this->getTable($tab, true);
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
                $data = $form->getData();
                $this->taskManager->taskValidateRequest($currentTask, $revisionId, $data['comment']);

                return new JsonResponse([
                    'html' => $ajaxTemplate->renderBlock('currentTask', [
                        'task' => $currentTask,
                        'revisionId' => $revisionId,
                    ]),
                ]);
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

        if ($this->taskManager->isTaskOwnerRevision($revision)) {
            $action = $request->get('action');
            $formValidation = $this->createCommentForm('validation', 'approve' !== $action);
            $formValidation->handleRequest($request);

            if ($formValidation->isSubmitted() && $formValidation->isValid()) {
                $comment = $formValidation->getData()['comment'];
                $this->taskManager->taskValidate($revision, 'approve' === $action, $comment);

                return $this->redirectToRoute('ems_core_task_ajax_tasks', ['revisionId' => $revisionId]);
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

    public function ajaxModalTask(string $taskId): JsonResponse
    {
        return $this->getAjaxModal()
            ->setFooter('modalFooterClose')
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
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['e' => $e]);
                $ajaxModal->addMessageError('task.error.ajax', [], $e);
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
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['e' => $e]);
                $ajaxModal->addMessageError('task.error.ajax', [], $e);
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

    public function ajaxModalChangeOwner(Request $request, int $revisionId): JsonResponse
    {
        $revision = $this->taskManager->getRevision($revisionId);
        $contentType = $revision->giveContentType();

        $ajaxModal = $this->getAjaxModal();
        $ajaxModal->setTitle('task.change_owner.title', ['%revision%' => $revision->getLabel()]);

        $form = $this->formFactory->createNamed('task_change_owner', FormType::class, [], [
            'translation_domain' => 'EMSCoreBundle',
        ]);
        $form->add('new_owner', SelectUserPropertyType::class, [
            'constraints' => [new NotBlank()],
            'user_roles' => [$contentType->getOwnerRole()],
            'exclude_values' => $revision->hasOwner() ? [$revision->getOwner()] : [],
            'placeholder' => '',
            'label' => 'task.change_owner.change',
            'allow_add' => false,
            'user_property' => 'username',
            'label_property' => 'displayName',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->changeOwner($revision, $form->get('new_owner')->getData());

                return $ajaxModal
                    ->addMessageSuccess('task.change_owner.success')
                    ->setBodyHtml('')
                    ->setFooter('modalFooterClose')
                    ->getResponse();
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['e' => $e]);
                $ajaxModal->addMessageError('task.error.ajax', [], $e);
            }
        }

        return $ajaxModal
            ->setBody('modalChangeOwnerBody', ['revision' => $revision, 'form' => $form->createView()])
            ->setFooter('modalChangeOwnerFooter')
            ->getResponse();
    }

    public function ajaxDelete(int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $ajaxModal = $this->getAjaxModal()
            ->setTitle('task.delete.title', ['%title%' => $task->getTitle()])
            ->setBodyHtml('')
            ->setFooter('modalFooterClose');

        try {
            $this->taskManager->taskDelete($task, $revisionId);
            $ajaxModal->addMessageSuccess('task.delete.success', ['%title%' => $task->getTitle()]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['e' => $e]);
            $ajaxModal->addMessageError('task.error.ajax');
        }

        return $ajaxModal->getResponse();
    }

    public function ajaxReorder(Request $request, int $revisionId): JsonResponse
    {
        $content = $request->getContent();
        $data = \is_string($content) ? Json::decode($content) : [];
        $taskIds = $data['taskIds'] ?? [];

        $this->taskManager->tasksReorder($revisionId, $taskIds);

        return new JsonResponse([], Response::HTTP_ACCEPTED);
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

    private function getTable(string $tab, bool $export = false): EntityTable
    {
        $tableUrl = $this->generateUrl('ems_core_task_ajax_datatable', ['tab' => $tab]);

        return $this->taskManager->getTable($tableUrl, $tab, $export);
    }
}

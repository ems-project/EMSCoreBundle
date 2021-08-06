<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Form\Form\RevisionTaskType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Twig\TemplateWrapper;

final class TaskController extends AbstractController
{
    private TaskManager $taskManager;
    private AjaxService $ajax;
    private FormFactoryInterface $formFactory;
    private LoggerInterface $logger;

    public function __construct(
        TaskManager $taskManager,
        AjaxService $ajax,
        FormFactoryInterface $formFactory,
        LoggerInterface $logger
    ) {
        $this->taskManager = $taskManager;
        $this->ajax = $ajax;
        $this->formFactory = $formFactory;
        $this->logger = $logger;
    }

    /**
     * @Route("/task/datatable.json", name="revision.task.dashboard.json")
     */
    public function ajaxDataTableAction(Request $request): Response
    {
        $table = $this->taskManager->getTable($this->generateUrl('revision.task.dashboard.json'));
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    /**
     * @Route("/dashboard/tasks", name="revision.task.dashboard")
     */
    public function dashboard(Request $request): Response
    {
        $table = $this->taskManager->getTable($this->generateUrl('revision.task.dashboard.json'));

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render('@EMSCore/revision/task/dashboard.html.twig', [
            'table' => $form->createView(),
        ]);
    }

    /**
     * @Route("/tasks/{revisionId}/current", name="revision.task", methods={"GET", "POST"})
     */
    public function getTask(Request $request, int $revisionId): JsonResponse
    {
        $currentTask = $this->taskManager->getCurrentTask($revisionId);
        $ajaxTemplate = $this->getAjaxTemplate();

        if ($currentTask && $this->taskManager->canRequestValidation($currentTask)) {
            $form = $this->formFactory->createNamed('request_validation');
            $form->add('comment', TextareaType::class, [
                'attr' => ['rows' => 4],
                'constraints' => [new NotBlank()],
            ]);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $this->taskManager->requestValidation($currentTask, $revisionId, $data['comment']);

                return new JsonResponse([
                    'html' => $ajaxTemplate->renderBlock('currentTask', ['task' => $currentTask]),
                ]);
            }
        }

        return new JsonResponse([
            'html' => $ajaxTemplate->renderBlock('currentTask', [
                'task' => $currentTask,
                'form' => isset($form) ? $form->createView() : null,
            ]),
        ]);
    }

    /**
     * @Route("/tasks/{revisionId}/list", name="revision.tasks", methods={"GET", "POST"})
     */
    public function getTasks(int $revisionId): JsonResponse
    {
        $taskCollection = $this->taskManager->getTaskCollection($revisionId);
        $revision = $taskCollection->getRevision();
        $ajaxTemplate = $this->getAjaxTemplate();

        $tasks = [];

        foreach ($taskCollection->getTasks() as $task) {
            $tasks[] = [
                'html' => $ajaxTemplate->renderBlock('taskItem', [
                    'task' => $task,
                    'revision' => $revision,
                    'isCurrent' => $revision->isTaskCurrent($task),
                ]),
            ];
        }

        return new JsonResponse(['tasks' => $tasks]);
    }

    /**
     * @Route("/tasks/{revisionId}/create-modal", name="revision.tasks.create-modal", methods={"GET", "POST"})
     */
    public function createModal(Request $request, int $revisionId): JsonResponse
    {
        $taskDTO = new TaskDTO();
        $ajaxModal = $this->getAjaxModal();

        $form = $this->createForm(RevisionTaskType::class, $taskDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $task = $this->taskManager->create($taskDTO, $revisionId);

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

    /**
     * @Route("/tasks/{revisionId}/update-modal/{taskId}", name="revision.tasks.update-modal", methods={"GET", "POST"})
     */
    public function updateModal(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $taskDTO = TaskDTO::fromEntity($task);

        $ajaxModal = $this->getAjaxModal();
        $ajaxModal->setFooter('modalUpdateFooter', ['task' => $task, 'revisionId' => $revisionId]);

        $form = $this->createForm(RevisionTaskType::class, $taskDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->update($task, $taskDTO, $revisionId);

                return $ajaxModal
                    ->setTitle('task.update.title', ['%title%' => $task->getTitle()])
                    ->addMessageSuccess('task.update.success', ['%title%' => $task->getTitle()])
                    ->setBody('modalUpdateBody', ['form' => $form->createView()])
                    ->getResponse();
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage(), ['e' => $e]);
                $ajaxModal->addMessageError('task.error.ajax', [], $e);
            }
        }

        return $ajaxModal
            ->setBody('modalUpdateBody', ['form' => $form->createView()])
            ->getResponse();
    }

    /**
     * @Route("/tasks/{revisionId}/delete/{taskId}", name="revision.tasks.delete", methods={"POST"})
     */
    public function deleteTask(int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $ajaxModal = $this->getAjaxModal()
            ->setTitle('task.delete.title', ['%title%' => $task->getTitle()])
            ->setBodyHtml('')
            ->setFooter('modalFooterClose');

        try {
            $this->taskManager->delete($task, $revisionId);
            $ajaxModal->addMessageSuccess('task.delete.success', ['%title%' => $task->getTitle()]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['e' => $e]);
            $ajaxModal->addMessageError('task.error.ajax');
        }

        return $ajaxModal->getResponse();
    }

    private function getAjaxModal(): AjaxModal
    {
        return $this->ajax->newAjaxModel('@EMSCore/revision/task/ajax.twig');
    }

    private function getAjaxTemplate(): TemplateWrapper
    {
        return $this->ajax->getTemplating()->load('@EMSCore/revision/task/ajax.twig');
    }
}

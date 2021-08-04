<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Form\Form\RevisionTaskType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Twig\TemplateWrapper;

final class TaskController extends AbstractController
{
    private TaskManager $taskManager;
    private AjaxService $ajax;

    public function __construct(TaskManager $taskManager, AjaxService $ajax)
    {
        $this->taskManager = $taskManager;
        $this->ajax = $ajax;
    }

    /**
     * @Route("/tasks/{revisionId}", name="revision.tasks", methods={"GET", "POST"})
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
                    'isCurrent' => $revision->getTasks()->isCurrent($task),
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

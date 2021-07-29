<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Task;
use EMS\CoreBundle\Form\Form\RevisionTaskType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\TemplateWrapper;

final class TaskController extends AbstractController
{
    private TaskManager $taskManager;
    private Environment $templating;
    private TranslatorInterface $translator;

    public function __construct(TaskManager $taskManager, Environment $templating, TranslatorInterface $translator)
    {
        $this->taskManager = $taskManager;
        $this->templating = $templating;
        $this->translator = $translator;
    }

    /**
     * @Route("/tasks/{revisionId}", name="revision.tasks", methods={"GET", "POST"})
     */
    public function getTasks(int $revisionId): JsonResponse
    {
        $taskCollection = $this->taskManager->getTaskCollection($revisionId);
        $revision = $taskCollection->getRevision();
        $ajaxTemplate = $this->getAjaxTemplate();

        return new JsonResponse([
            'tasks' => \array_map(function (Task $task) use ($ajaxTemplate, $revision) {
                return [
                    'html' => $ajaxTemplate->renderBlock('taskItem', [
                        'revision' => $revision,
                        'task' => $task,
                    ]),
                ];
            }, $taskCollection->getTasks()),
        ]);
    }

    /**
     * @Route("/tasks/{revisionId}/create-modal", name="revision.tasks.create-modal", methods={"GET", "POST"})
     */
    public function createModal(Request $request, int $revisionId): JsonResponse
    {
        try {
            return $this->ajaxModal('create', $request, new Task(), $revisionId);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'messages' => [['error' => $e]],
            ]);
        }
    }

    /**
     * @Route("/tasks/{revisionId}/update-modal/{taskId}", name="revision.tasks.update-modal", methods={"GET", "POST"})
     */
    public function updateModal(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        try {
            $task = $this->taskManager->getTask($taskId);

            return $this->ajaxModal('update', $request, $task, $revisionId);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'messages' => [['error' => $e->getMessage()]],
            ]);
        }
    }

    private function ajaxModal(string $type, Request $request, Task $task, int $revisionId): JsonResponse
    {
        $form = $this->createForm(RevisionTaskType::class, $task);
        $form->handleRequest($request);

        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ('create' === $type) {
                    $this->taskManager->create($task, $revisionId);
                } elseif ('update' === $type) {
                    $this->taskManager->update($task, $revisionId);
                }

                return new JsonResponse([
                    'messages' => [['success' => $this->translator->trans(\sprintf('task.%s.success', $type))]],
                    'body' => null,
                    'buttons' => null,
                ]);
            } catch (\Throwable $e) {
                $messages[] = ['error' => $this->translator->trans(\sprintf('task.%s.failed', $type))];
            }
        }

        $ajaxTemplate = $this->getAjaxTemplate();

        return new JsonResponse([
            'messages' => $messages,
            'body' => $ajaxTemplate->renderBlock(\sprintf('modal%sBody', \ucfirst($type)), ['form' => $form->createView()]),
            'buttons' => $ajaxTemplate->renderBlock(\sprintf('modal%sButtons', \ucfirst($type))),
        ]);
    }

    private function getAjaxTemplate(): TemplateWrapper
    {
        return $this->templating->load('@EMSCore/revision/task/ajax.twig');
    }
}

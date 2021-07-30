<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
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
        $taskDTO = new TaskDTO();

        $form = $this->createForm(RevisionTaskType::class, $taskDTO);
        $form->handleRequest($request);

        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $task = $this->taskManager->create($taskDTO, $revisionId);

                return new JsonResponse([
                    'modalMessages' => [['success' => $this->translator->trans('task.create.success', [
                        '%title%' => $task->getTitle(),
                    ], 'EMSCoreBundle')]],
                    'modalBody' => null,
                ]);
            } catch (\Throwable $e) {
                $messages[] = ['error' => $this->translator->trans('task.error.ajax', [], 'EMSCoreBundle')];
            }
        }

        $ajaxTemplate = $this->getAjaxTemplate();

        return new JsonResponse([
            'modalMessages' => $messages,
            'modalBody' => $ajaxTemplate->renderBlock('modalCreateBody', ['form' => $form->createView()]),
            'modalFooter' => $ajaxTemplate->renderBlock('modalCreateFooter'),
        ]);
    }

    /**
     * @Route("/tasks/{revisionId}/update-modal/{taskId}", name="revision.tasks.update-modal", methods={"GET", "POST"})
     */
    public function updateModal(Request $request, int $revisionId, string $taskId): JsonResponse
    {
        $task = $this->taskManager->getTask($taskId);
        $taskDTO = TaskDTO::fromEntity($task);
        $ajaxTemplate = $this->getAjaxTemplate();

        $form = $this->createForm(RevisionTaskType::class, $taskDTO);
        $form->handleRequest($request);

        $messages = [];
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->update($task, $taskDTO, $revisionId);

                return new JsonResponse([
                    'modalMessages' => [['success' => $this->translator->trans('task.update.success', [
                        '%title%' => $task->getTitle(),
                    ], 'EMSCoreBundle')]],
                    'modalTitle' => $this->translator->trans('task.update.title', [
                        '%title%' => $task->getTitle(),
                    ], 'EMSCoreBundle'),
                    'modalBody' => $ajaxTemplate->renderBlock('modalUpdateBody', ['form' => $form->createView()]),
                    'modalFooter' => $ajaxTemplate->renderBlock('modalUpdateFooter'),
                ]);
            } catch (\Throwable $e) {
                $messages[] = ['error' => $this->translator->trans('task.error.ajax', [], 'EMSCoreBundle')];
            }
        }

        return new JsonResponse([
            'modalMessages' => $messages,
            'modalBody' => $ajaxTemplate->renderBlock('modalUpdateBody', ['form' => $form->createView()]),
            'modalFooter' => $ajaxTemplate->renderBlock('modalUpdateFooter'),
        ]);
    }

    private function getAjaxTemplate(): TemplateWrapper
    {
        return $this->templating->load('@EMSCore/revision/task/ajax.twig');
    }
}

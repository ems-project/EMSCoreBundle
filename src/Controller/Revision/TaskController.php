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
     * @Route("/task/add-modal/{revisionId}", name="revision.task.add-modal", methods={"GET", "POST"})
     */
    public function addModal(Request $request, int $revisionId): JsonResponse
    {
        $task = new Task();
        $form = $this->createForm(RevisionTaskType::class, $task);

        $modalTemplate = $this->templating->load('@FOSUser/revision/task/modal-add.html.twig');

        $messages = [];
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->taskManager->create($task, $revisionId);
                return new JsonResponse([
                    'messages' => [ ['success' => $this->translator->trans('task.creation.success') ] ],
                    'body' => null,
                    'buttons' => null,
                ]);
            } catch (\Throwable $e) {
                $messages[] = ['error' => $this->translator->trans('task.creation.failed') ];
            }
        }

        return new JsonResponse([
            'messages' => $messages,
            'body' => $modalTemplate->renderBlock('modalBody', ['form' => $form->createView()]),
            'buttons' => $modalTemplate->renderBlock('modalButtons'),
        ]);
    }
}
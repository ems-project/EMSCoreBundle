<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Core\Revision\Task\TaskStatus;
use EMS\CoreBundle\Core\UI\AjaxModal;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskHandleType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskType;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly RevisionService $revisionService,
        private readonly AjaxService $ajax,
        private readonly FormFactoryInterface $formFactory,
        private readonly string $coreDateFormat,
        private readonly string $templateNamespace,
    ) {
    }

    public function ajaxGetTasks(Request $request, UserInterface $user, string $revisionOuuid): Response
    {
        $revision = $this->revisionService->give($revisionOuuid);
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
                        'send' => $this->taskManager->taskValidateRequest($revision->getTaskCurrent(), $revision, $comment),
                        'approve' => $this->taskManager->taskValidate($revision, true, $comment),
                        'reject' => $this->taskManager->taskValidate($revision, false, $comment),
                    };

                    return new JsonResponse(['success' => true]);
                } catch (\Throwable) {
                    return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
                }
            }
        }

        return new JsonResponse([
            'tab' => $ajaxTemplate->renderBlock('tasksTab', \array_filter([
                'formHandle' => isset($formHandle) ? $formHandle->createView() : null,
                'revision' => $revision,
                'tasksPlanned' => $this->taskManager->getTasksPlanned($revision),
                'tasksApproved' => $this->taskManager->getTasksApproved($revision),
            ])),
        ]);
    }

    public function ajaxModalTask(Request $request, string $revisionOuuid, string $taskId): JsonResponse
    {
        try {
            $revision = $this->revisionService->give($revisionOuuid);

            return $this->getAjaxModal()
                ->setFooter('modalFooterActions', [
                    'ouuid' => $revision->getOuuid(),
                    'revisionId' => $revision->getId(),
                    'contentType' => $revision->getContentType(),
                    'isManager' => $this->taskManager->isTaskManager(),
                    'fromRevision' => $request->query->getBoolean('fromRevision'),
                ])
                ->setBody('modalTaskBody', ['task' => $this->taskManager->getTask($taskId, $revision)])
                ->getResponse();
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }
    }

    public function ajaxModalCreate(Request $request, string $revisionOuuid): JsonResponse
    {
        try {
            $taskDTO = new TaskDTO($this->coreDateFormat);
            $ajaxModal = $this->getAjaxModal();
            $revision = $this->revisionService->give($revisionOuuid);

            $form = $this->createForm(RevisionTaskType::class, $taskDTO, [
                'content_type' => $revision->giveContentType(),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $task = $this->taskManager->taskCreate($taskDTO, $revision);

                return $ajaxModal
                    ->addMessageSuccess('task.create.success', ['%title%' => $task->getTitle()])
                    ->setBodyHtml('')
                    ->setFooter('modalFooterClose')
                    ->getResponse();
            }

            return $ajaxModal
                ->setBody('modalCreateBody', ['form' => $form->createView()])
                ->setFooter('modalCreateFooter')
                ->getResponse();
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }
    }

    public function ajaxModalUpdate(Request $request, UserInterface $user, string $revisionOuuid, string $taskId): JsonResponse
    {
        try {
            $revision = $this->revisionService->give($revisionOuuid);
            $task = $this->taskManager->getTask($taskId, $revision);
            if (!$task->isRequester($user) && !$this->isGranted('ROLE_TASK_MANAGER')) {
                throw $this->createAccessDeniedException();
            }

            $taskDTO = TaskDTO::fromEntity($task, $this->coreDateFormat);

            $ajaxModal = $this->getAjaxModal();
            $ajaxModal->setFooter('modalUpdateFooter', ['task' => $task, 'revisionOuuid' => $revision->getOuuid()]);

            $form = $this->createForm(RevisionTaskType::class, $taskDTO, [
                'task_status' => TaskStatus::from($task->getStatus()),
                'content_type' => $revision->giveContentType(),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->taskManager->taskUpdate($task, $taskDTO, $revision);

                return $ajaxModal
                    ->setTitle('task.update.title', ['%title%' => $task->getTitle()])
                    ->addMessageSuccess('task.update.success', ['%title%' => $task->getTitle()])
                    ->setBody('modalTaskBody', ['form' => $form->createView(), 'task' => $task])
                    ->getResponse();
            }

            return $ajaxModal
                ->setBody('modalTaskBody', ['form' => $form->createView(), 'task' => $task])
                ->getResponse();
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }
    }

    public function ajaxModalDelete(Request $request, UserInterface $user, string $revisionOuuid, string $taskId): JsonResponse
    {
        try {
            $revision = $this->revisionService->give($revisionOuuid);
            $task = $this->taskManager->getTask($taskId, $revision);
            if (!$task->isRequester($user) && !$this->isGranted('ROLE_TASK_MANAGER')) {
                throw $this->createAccessDeniedException();
            }

            $ajaxModal = $this->getAjaxModal()->setTitle('task.delete.title', ['%title%' => $task->getTitle()]);

            $form = $this->formFactory->create();
            $form->add('comment', TextareaType::class, [
                'attr' => ['rows' => 4],
                'constraints' => new NotBlank(),
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $this->taskManager->taskDelete($task, $revision, $form->getData()['comment']);

                return $ajaxModal
                    ->addMessageSuccess('task.delete.success', ['%title%' => $task->getTitle()])
                    ->setBodyHtml('')
                    ->setFooter('modalFooterClose')
                    ->getResponse();
            }

            return $ajaxModal
                ->setBody('modalDeleteBody', ['form' => $form->createView(), 'task' => $task])
                ->setFooter('modalDeleteFooter')
                ->getResponse();
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }
    }

    public function ajaxReorder(Request $request, string $revisionOuuid): JsonResponse
    {
        try {
            $revision = $this->revisionService->give($revisionOuuid);
            $data = Json::decode($request->getContent());
            $taskIds = $data['taskIds'] ?? [];

            $this->taskManager->tasksReorder($revision, $taskIds);

            return new JsonResponse([], Response::HTTP_ACCEPTED);
        } catch (\Throwable) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
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
}

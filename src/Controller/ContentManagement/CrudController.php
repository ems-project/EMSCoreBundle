<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CrudController extends AbstractController
{
    private UserService $userService;
    private LoggerInterface $logger;
    private DataService $dataService;
    private ContentTypeService $contentTypeService;

    public function __construct(LoggerInterface $logger, UserService $userService, DataService $dataService, ContentTypeService $contentTypeService)
    {
        $this->logger = $logger;
        $this->userService = $userService;
        $this->dataService = $dataService;
        $this->contentTypeService = $contentTypeService;
    }

    public function createAction(?string $ouuid, string $name, Request $request): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not create content for a managed content type');
        }

        $rawdata = Json::decode(Type::string($request->getContent()));
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $newRevision = $this->dataService->createData($ouuid, $rawdata, $contentType);
        } catch (\Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->logger->error('log.crud.create_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => false,
                    'ouuid' => $ouuid,
                    'type' => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => true,
                'revision_id' => $newRevision->getId(),
                'ouuid' => $newRevision->getOuuid(),
        ]);
    }

    public function getAction(string $ouuid, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
        } catch (\Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->logger->error('log.crud.read_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }

            return $this->render('@EMSCore/ajax/revision.json.twig', [
                    'success' => false,
                    'ouuid' => $ouuid,
                    'type' => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/ajax/revision.json.twig', [
                'success' => true,
                'revision' => $revision->getRawData(),
                'ouuid' => $revision->getOuuid(),
                'id' => $revision->getId(),
        ]);
    }

    /**
     * @param int $id
     */
    public function finalizeAction($id, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not finalize content for a managed content type');
        }

        $out = [
            'success' => 'false',
        ];
        try {
            $revision = $this->dataService->getRevisionById($id, $contentType);
            $newRevision = $this->dataService->finalizeDraft($revision);
            $out['success'] = !$newRevision->getDraft();
            $out['ouuid'] = $newRevision->getOuuid();
        } catch (\Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof DataStateException)) {
                throw $e;
            } else {
                $this->logger->error('log.crud.finalize_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }
            $out['success'] = false;
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', $out);
    }

    /**
     * @param int $id
     */
    public function discardAction($id, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not discard content for a managed content type');
        }

        try {
            $revision = $this->dataService->getRevisionById($id, $contentType);
            $this->dataService->discardDraft($revision);
            $isDiscard = ($revision->getId() != $id) ? true : false;
        } catch (\Exception $e) {
            $isDiscard = false;
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->logger->error('log.crud.discard_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => $isDiscard,
                    'type' => $contentType->getName(),
                    'revision_id' => $id,
            ]);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $isDiscard,
                'type' => $contentType->getName(),
                'revision_id' => $revision->getId(),
        ]);
    }

    public function deleteAction(string $ouuid, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        $isDeleted = false;
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not delete content for a managed content type');
        }

        try {
            $this->dataService->delete($contentType->getName(), $ouuid);
            $this->logger->notice('log.crud.deleted', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
            ]);
            $isDeleted = true;
        } catch (\Exception $e) {
            if (($e instanceof NotFoundHttpException) || ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->logger->error('log.crud.delete_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_OUUID_FIELD => $ouuid,
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $isDeleted,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
        ]);
    }

    public function replaceAction(string $ouuid, string $name, Request $request): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not replace content for a managed content type');
        }

        $rawdata = Json::decode(Type::string($request->getContent()));
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $this->dataService->replaceData($revision, $rawdata);
            $isReplaced = ($revision->getId() != $newDraft->getId()) ? true : false;
        } catch (\Exception $e) {
            $isReplaced = false;
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            } else {
                $this->logger->error('log.crud.replace_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => $isReplaced,
                    'ouuid' => $ouuid,
                    'type' => $contentType->getName(),
                    'revision_id' => null,
            ]);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $isReplaced,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
                'revision_id' => $newDraft->getId(),
        ]);
    }

    public function mergeAction(string $ouuid, string $name, Request $request): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not merge content for a managed content type');
        }

        $rawdata = Json::decode(Type::string($request->getContent()));
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message for revision '.$ouuid.' and contenttype '.$contentType->getName());
        }

        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $this->dataService->replaceData($revision, $rawdata, 'merge');
            $isMerged = ($revision->getId() != $newDraft->getId()) ? true : false;
        } catch (\Exception $e) {
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            } else {
                $this->logger->error('log.crud.merge_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
            }
            $isMerged = false;

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => $isMerged,
                    'ouuid' => $ouuid,
                    'type' => $contentType->getName(),
                    'revision_id' => null,
            ]);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => $isMerged,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
                'revision_id' => $newDraft->getId(),
        ]);
    }

    public function testAction(): Response
    {
        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => true,
        ]);
    }

    public function getContentTypeInfo(string $name): Response
    {
        $contentType = $this->giveContentType($name);

        return $this->render('@EMSCore/ajax/contenttype_info.json.twig', [
                'success' => true,
                'contentType' => $contentType,
        ]);
    }

    public function getUserProfile(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('User profile class not recognized');
        }
        if (!$user->isEnabled()) {
            throw new \RuntimeException('User disabled');
        }

        return $this->json($user->toArray());
    }

    public function getUserProfiles(): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER_READ')
            || !$this->isGranted('ROLE_USER_MANAGEMENT')
            || !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException();
        }
        $users = [];
        foreach ($this->userService->getAllUsers() as $user) {
            if ($user->isEnabled()) {
                $users[] = $user->toArray();
            }
        }

        return $this->json($users);
    }

    private function giveContentType(string $contentTypeName): ContentType
    {
        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (false === $contentType) {
            throw new \RuntimeException('Unexpected false content type');
        }
        if ($contentType->getDeleted()) {
            throw new \RuntimeException('Unexpected deleted content type');
        }
        if (!$contentType->getActive()) {
            throw new \RuntimeException('Unexpected inactive content type');
        }

        return $contentType;
    }
}

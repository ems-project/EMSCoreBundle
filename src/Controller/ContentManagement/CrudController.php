<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
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
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserService $userService,
        private readonly DataService $dataService,
        private readonly ContentTypeService $contentTypeService,
        private readonly FlashMessageLogger $flashMessageLogger,
    ) {
    }

    public function create(?string $ouuid, string $name, Request $request): Response
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

            if ($request->query->getBoolean('refresh')) {
                $this->dataService->refresh($newRevision->giveContentType()->giveEnvironment());
            }
        } catch (\Exception $e) {
            if ($e instanceof NotFoundHttpException || $e instanceof BadRequestHttpException) {
                throw $e;
            }

            $this->logger->error('log.crud.create_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'revision_id' => $newRevision->getId(),
            'ouuid' => $newRevision->getOuuid(),
        ]);
    }

    public function get(string $ouuid, string $name): Response
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

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'revision' => $revision->getRawData(),
            'ouuid' => $revision->getOuuid(),
            'id' => $revision->getId(),
        ]);
    }

    /**
     * @param int $id
     */
    public function finalize($id, string $name): Response
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

        return $this->flashMessageLogger->buildJsonResponse($out);
    }

    /**
     * @param int $id
     */
    public function discard($id, string $name): Response
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

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => $isDiscard,
                'type' => $contentType->getName(),
                'revision_id' => $id,
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $isDiscard,
            'type' => $contentType->getName(),
            'revision_id' => $revision->getId(),
        ]);
    }

    public function delete(string $ouuid, string $name): Response
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

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $isDeleted,
            'ouuid' => $ouuid,
            'type' => $contentType->getName(),
        ]);
    }

    public function replace(string $ouuid, string $name, Request $request): Response
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

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => $isReplaced,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
                'revision_id' => null,
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $isReplaced,
            'ouuid' => $ouuid,
            'type' => $contentType->getName(),
            'revision_id' => $newDraft->getId(),
        ]);
    }

    public function merge(string $ouuid, string $name, Request $request): Response
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

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => $isMerged,
                'ouuid' => $ouuid,
                'type' => $contentType->getName(),
                'revision_id' => null,
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $isMerged,
            'ouuid' => $ouuid,
            'type' => $contentType->getName(),
            'revision_id' => $newDraft->getId(),
        ]);
    }

    public function test(): Response
    {
        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
        ]);
    }

    public function getContentTypeInfo(string $name): Response
    {
        $contentType = $this->giveContentType($name);

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'singular_name' => $contentType->getSingularName(),
            'plural_name' => $contentType->getPluralName(),
            'default_alias' => $contentType->giveEnvironment()->getAlias(),
            'default_name' => $contentType->giveEnvironment()->getName(),
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
            && !$this->isGranted('ROLE_USER_MANAGEMENT')
            && !$this->isGranted('ROLE_ADMIN')) {
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

    public function index(Request $request, string $name, ?string $ouuid = null, string $replaceOrMerge = 'replace'): JsonResponse
    {
        $revision = null;
        if (null !== $ouuid) {
            try {
                $revision = $this->dataService->getNewestRevision($name, $ouuid);
            } catch (NotFoundHttpException) {
            }
        }

        $rawData = Json::decode(Type::string($request->getContent()));
        if (null === $revision) {
            $contentType = $this->contentTypeService->giveByName($name);
            $revision = $this->dataService->createData($ouuid, $rawData, $contentType);
        } else {
            $revision = $this->dataService->replaceData($revision, $rawData, $replaceOrMerge);
        }

        $this->dataService->finalizeDraft($revision);

        if ($request->query->getBoolean('refresh')) {
            $this->dataService->refresh($revision->giveContentType()->giveEnvironment());
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => !$revision->getDraft(),
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->giveContentType()->getName(),
            'revision_id' => $revision->getId(),
        ]);
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

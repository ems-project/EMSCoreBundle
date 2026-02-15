<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Revision\RevisionService;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CrudController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserService $userService,
        private readonly DataService $dataService,
        private readonly ContentTypeService $contentTypeService,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly RevisionService $revisionService,
        private readonly StorageManager $storageManager,
        private readonly EnvironmentService $environmentService,
    ) {
    }

    public function create(?string $ouuid, string $name, Request $request): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not create content for a managed content type');
        }

        $rawdata = Json::decode(Type::string($request->getContent()));
        if ([] === $rawdata) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $newRevision = $this->dataService->createData($ouuid, $rawdata, $contentType);

            if ($request->query->getBoolean('refresh')) {
                $this->dataService->refresh($newRevision->giveContentType()->giveEnvironment());
            }
        } catch (\Exception $exception) {
            if ($exception instanceof NotFoundHttpException || $exception instanceof BadRequestHttpException) {
                throw $exception;
            }

            $this->logger->error('log.crud.create_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
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

    public function autoSave(Request $request, int $revisionId): JsonResponse
    {
        $this->revisionService->autoSave(
            revision: $this->revisionService->getByRevisionId($revisionId),
            autoSave: Json::decode(Type::string($request->getContent()))
        );

        return $this->flashMessageLogger->buildJsonResponse(['success' => true]);
    }

    public function get(string $ouuid, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
        } catch (\Exception $exception) {
            if ($exception instanceof NotFoundHttpException || $exception instanceof BadRequestHttpException) {
                throw $exception;
            }
            $this->logger->error('log.crud.read_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);

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

    public function environments(string $name, string $ouuid): JsonResponse
    {
        $revision = $this->dataService->getNewestRevision($name, $ouuid);
        $environments = $this->environmentService->getPublishedForRevision($revision, true);

        return new JsonResponse($environments->map(fn (Environment $e) => [
            'name' => $e->getName(),
            'label' => $e->getLabel(),
            'snapshot' => $e->getSnapshot(),
        ])->toArray());
    }

    public function getDraft(int $revisionId): JsonResponse
    {
        try {
            $revision = $this->revisionService->getByRevisionId($revisionId);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Revision not found');
        }

        if (!$revision->isDraft()) {
            throw new HttpException(Response::HTTP_NOT_ACCEPTABLE, 'not in draft');
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'id' => $revision->getId(),
            'data' => $revision->getDraftData(),
        ]);
    }

    public function finalize(Request $request, int $id, string $name): Response
    {
        try {
            $contentType = $this->giveContentType($name)->validate();
            $revision = $this->dataService->getRevisionById($id, $contentType);

            $content = $request->getContent();
            $rawData = '' !== (string) $content ? Json::decode(Type::string($content)) : [];
            if ([] !== $rawData) {
                $this->revisionService->autoSave($revision, $rawData);
            }

            $revision->autoSaveToRawData();

            $newRevision = $this->dataService->finalizeDraft($revision);

            $this->dataService->refresh($contentType->giveEnvironment());

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => !$newRevision->getDraft(),
                'ouuid' => $newRevision->getOuuid(),
            ]);
        } catch (\Exception $exception) {
            if ($exception instanceof NotFoundHttpException || $exception instanceof DataStateException) {
                throw $exception;
            }

            $this->logger->error('log.crud.finalize_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);

            return $this->flashMessageLogger->buildJsonResponse(['success' => false]);
        }
    }

    public function discard(int $id, string $name): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not discard content for a managed content type');
        }

        try {
            $revision = $this->dataService->getRevisionById($id, $contentType);
            $this->dataService->discardDraft($revision);
            $isDiscard = $revision->getId() !== $id;
        } catch (\Exception $exception) {
            $isDiscard = false;
            if ($exception instanceof NotFoundHttpException || $exception instanceof BadRequestHttpException) {
                throw $exception;
            }
            $this->logger->error('log.crud.discard_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);

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
        $isDeleted = false;

        try {
            $this->dataService->delete($name, $ouuid);
            $this->logger->notice('log.crud.deleted', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $name,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
            ]);
            $isDeleted = true;
        } catch (\Exception $exception) {
            if ($exception instanceof NotFoundHttpException || $exception instanceof BadRequestHttpException) {
                throw $exception;
            }

            $this->logger->error('log.crud.delete_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $name,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => $isDeleted,
            'ouuid' => $ouuid,
            'type' => $name,
        ]);
    }

    public function replace(string $ouuid, string $name, Request $request): Response
    {
        $contentType = $this->giveContentType($name);
        if (!$contentType->giveEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not replace content for a managed content type');
        }

        $rawdata = Json::decode(Type::string($request->getContent()));
        if ([] === $rawdata) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $this->dataService->replaceData($revision, $rawdata);
            $isReplaced = $revision->getId() !== $newDraft->getId();
        } catch (\Exception $exception) {
            $isReplaced = false;
            if ($exception instanceof NotFoundHttpException) {
                throw $exception;
            }
            $this->logger->error('log.crud.replace_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);

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
        if ([] === $rawdata) {
            throw new BadRequestHttpException('Not a valid JSON message for revision '.$ouuid.' and contenttype '.$contentType->getName());
        }

        try {
            $revision = $this->dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $this->dataService->replaceData($revision, $rawdata, 'merge');
            $isMerged = $revision->getId() !== $newDraft->getId();
        } catch (\Exception $exception) {
            if ($exception instanceof NotFoundHttpException) {
                throw $exception;
            }
            $this->logger->error('log.crud.merge_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);

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

    /**
     * @param mixed[] $rawData
     */
    private function indexInternal(Request $request, array $rawData, string $name, ?string $ouuid, string $replaceOrMerge): JsonResponse
    {
        $lazyIndex = $request->query->getBoolean('lazy');
        $revision = null;
        if (null !== $ouuid) {
            try {
                $revision = $this->dataService->getNewestRevision($name, $ouuid);
                $revision->setLazyIndex($lazyIndex);
            } catch (NotFoundHttpException) {
            }
        }

        if (null === $revision) {
            $contentType = $this->contentTypeService->giveByName($name);
            $revision = $this->dataService->createData($ouuid, $rawData, $contentType);
            $revision->setLazyIndex($lazyIndex);
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

    public function index(Request $request, string $name, ?string $ouuid = null, string $replaceOrMerge = 'replace'): JsonResponse
    {
        $rawData = Json::decode(Type::string($request->getContent()));

        return $this->indexInternal($request, $rawData, $name, $ouuid, $replaceOrMerge);
    }

    public function indexFromAsset(Request $request, string $name, ?string $ouuid = null, string $replaceOrMerge = 'replace'): JsonResponse
    {
        $data = Json::decode(Type::string($request->getContent()));
        $hash = Type::string($data['hash'] ?? null);
        $rawData = Json::decode($this->storageManager->getContents($hash));

        return $this->indexInternal($request, $rawData, $name, $ouuid, $replaceOrMerge);
    }

    public function initDraft(string $uuid, string $name): JsonResponse
    {
        try {
            $contentType = $this->giveContentType($name)->validate();
            if (!$this->isGranted($contentType->role(ContentTypeRoles::EDIT))) {
                throw $this->createAccessDeniedException('Edit role not granted!');
            }

            $draftRevision = $this->dataService->initNewDraft($contentType, $uuid);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => true,
                'revision_id' => $draftRevision->getId(),
                'ouuid' => $draftRevision->getOuuid(),
            ]);
        } catch (\Throwable $throwable) {
            $this->logger->error('log.crud.create_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $name,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $throwable->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $throwable,
            ]);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
                'ouuid' => $uuid,
                'type' => $name,
            ]);
        }
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

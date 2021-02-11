<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\UserService;
use Exception;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class CrudController extends AppController
{
    /** @var UserService */
    private $userService;

    public function __construct(LoggerInterface $logger, FormRegistryInterface $formRegistry, RequestRuntime $requestRuntime, UserService $userService)
    {
        parent::__construct($logger, $formRegistry, $requestRuntime);
        $this->userService = $userService;
    }

    /**
     * @param string $ouuid
     *
     * @return Response
     *
     * @Route("/{interface}/data/{name}/create/{ouuid}", defaults={"ouuid": null, "_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @Route("/{interface}/data/{name}/draft/{ouuid}", defaults={"ouuid": null, "_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function createAction($ouuid, ContentType $contentType, Request $request, DataService $dataService)
    {
        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not create content for a managed content type');
        }

        /** @var string $content */
        $content = $request->getContent();
        $rawdata = \json_decode($content, true);
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $newRevision = $dataService->createData($ouuid, $rawdata, $contentType);
        } catch (Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.create_error', [
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

    /**
     * @param string $ouuid
     *
     * @return Response
     *
     * @Route("/{interface}/data/{name}/{ouuid}", defaults={"ouuid": null, "_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"GET"})
     * @Route("/{interface}/data/{name}/get/{ouuid}", defaults={"ouuid": null, "_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"GET"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function getAction($ouuid, ContentType $contentType, DataService $dataService)
    {
        try {
            $revision = $dataService->getNewestRevision($contentType->getName(), $ouuid);
        } catch (Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.read_error', [
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
     *
     * @return Response
     *
     * @throws DataStateException
     * @throws Throwable
     *
     * @Route("/{interface}/data/{name}/finalize/{id}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function finalizeAction($id, ContentType $contentType, DataService $dataService)
    {
        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not finalize content for a managed content type');
        }

        $out = [
            'success' => 'false',
        ];
        try {
            $revision = $dataService->getRevisionById($id, $contentType);
            $newRevision = $dataService->finalizeDraft($revision);
            $out['success'] = !$newRevision->getDraft();
            $out['ouuid'] = $newRevision->getOuuid();
        } catch (Exception $e) {
            if (($e instanceof NotFoundHttpException) or ($e instanceof DataStateException)) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.finalize_error', [
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
     * @return Response
     *
     * @Route("/{interface}/data/{name}/discard/{id}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function discardAction(int $id, ContentType $contentType, DataService $dataService)
    {
        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not discard content for a managed content type');
        }

        try {
            $revision = $dataService->getRevisionById($id, $contentType);
            $dataService->discardDraft($revision);
            $isDiscard = ($revision->getId() != $id) ? true : false;
        } catch (Exception $e) {
            $isDiscard = false;
            if (($e instanceof NotFoundHttpException) or ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.discard_error', [
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

    /**
     * @param string $ouuid
     *
     * @return Response
     *
     * @Route("/{interface}/data/{name}/delete/{ouuid}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function deleteAction($ouuid, ContentType $contentType, DataService $dataService)
    {
        $isDeleted = false;

        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not delete content for a managed content type');
        }

        try {
            $dataService->delete($contentType->getName(), $ouuid);
            $this->getLogger()->notice('log.crud.deleted', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
            ]);
            $isDeleted = true;
        } catch (Exception $e) {
            if (($e instanceof NotFoundHttpException) || ($e instanceof BadRequestHttpException)) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.delete_error', [
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

    /**
     * @param string $ouuid
     *
     * @return Response
     *
     * @Route("/{interface}/data/{name}/replace/{ouuid}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function replaceAction($ouuid, ContentType $contentType, Request $request, DataService $dataService)
    {
        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not replace content for a managed content type');
        }

        /** @var string $content */
        $content = $request->getContent();
        $rawdata = \json_decode($content, true);
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        try {
            $revision = $dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $dataService->replaceData($revision, $rawdata);
            $isReplaced = ($revision->getId() != $newDraft->getId()) ? true : false;
        } catch (Exception $e) {
            $isReplaced = false;
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.replace_error', [
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

    /**
     * @param string $ouuid
     *
     * @return Response
     *
     * @Route("/{interface}/data/{name}/merge/{ouuid}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function mergeAction($ouuid, ContentType $contentType, Request $request, DataService $dataService)
    {
        /** @var Environment $environment */
        $environment = $contentType->getEnvironment();

        if (!$environment->getManaged()) {
            throw new BadRequestHttpException('You can not merge content for a managed content type');
        }

        /** @var string $content */
        $content = $request->getContent();
        $rawdata = \json_decode($content, true);
        if (empty($rawdata)) {
            throw new BadRequestHttpException('Not a valid JSON message for revision '.$ouuid.' and contenttype '.$contentType->getName());
        }

        try {
            $revision = $dataService->getNewestRevision($contentType->getName(), $ouuid);
            $newDraft = $dataService->replaceData($revision, $rawdata, 'merge');
            $isMerged = ($revision->getId() != $newDraft->getId()) ? true : false;
        } catch (Exception $e) {
            if ($e instanceof NotFoundHttpException) {
                throw $e;
            } else {
                $this->getLogger()->error('log.crud.merge_error', [
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

    /**
     * @return Response
     *
     * @Route("/{interface}/test", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, name="api.test", methods={"GET"})
     */
    public function testAction()
    {
        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => true,
        ]);
    }

    /**
     * @return Response
     *
     * @Route("/{interface}/meta/{name}", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"GET"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function getContentTypeInfo(ContentType $contentType)
    {
        return $this->render('@EMSCore/ajax/contenttype_info.json.twig', [
                'success' => true,
                'contentType' => $contentType,
        ]);
    }

    /**
     * @Route("/{interface}/user-profile", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"GET"})
     */
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

    /**
     * @Route("/{interface}/user-profiles", defaults={"_format": "json", "interface": "api"}, requirements={"interface": "api|json"}, methods={"GET"})
     * @IsGranted({"ROLE_USER_READ", "ROLE_USER_MANAGEMENT", "ROLE_ADMIN"})
     */
    public function getUserProfiles(): JsonResponse
    {
        $users = [];
        foreach ($this->userService->getAllUsers() as $user) {
            if ($user->isEnabled()) {
                $users[] = $user->toArray();
            }
        }

        return $this->json($users);
    }
}

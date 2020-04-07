<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

final class IndexController extends AbstractController
{
    /** @var DataService */
    private $dataService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(DataService $dataService, LoggerInterface $logger)
    {
        $this->dataService = $dataService;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/index/{name}/{ouuid}", defaults={"ouuid": null, "_format": "json"}, methods={"POST"})
     * @ParamConverter("contentType", options={"mapping": {"name": "name", "deleted": 0, "active": 1}})
     */
    public function index(ContentType $contentType, Request $request, ?string $ouuid): Response
    {
        if (!$contentType->getEnvironment()->getManaged()) {
            throw new BadRequestHttpException('You can not create content for a managed content type');
        }

        $rawData = json_decode($request->getContent(), true);
        if (empty($rawData)) {
            throw new BadRequestHttpException('Not a valid JSON message');
        }

        $data = ['success' => false, 'type' => $contentType->getName()];

        try {
            if ($ouuid) {
                $draft = $this->dataService->initNewDraft($contentType->getName(), $ouuid);
            } else {
                $draft = $this->dataService->createData($ouuid, $rawData, $contentType);
            }

            $finalizedRevision = $this->dataService->finalizeDraft($draft);

            $data['success'] = true;
            $data['revision_id'] = $finalizedRevision->getId();
            $data['ouuid'] = $finalizedRevision->getOuuid();

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
            return $this->render('@EMSCore/ajax/notification.json.twig', $data);
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', $data);
    }
}
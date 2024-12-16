<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Wysiwyg;

use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\Helpers\Html\HtmlHelper;
use EMS\Helpers\Standard\Html;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AjaxPasteController
{
    public function __construct(
        private readonly WysiwygProfileService $wysiwygProfileService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, int $wysiwygProfileId): JsonResponse
    {
        if (null === $profile = $this->wysiwygProfileService->getById($wysiwygProfileId)) {
            throw new NotFoundHttpException('Wysiwyg profile not found');
        }

        $config = $profile->getConfig() ? Json::decode($profile->getConfig()) : [];
        $pasteConfig = $config['ems']['paste'] ?? [];
        $content = Json::decode($request->getContent())['content'] ?? '';

        if ('' === $content || !HtmlHelper::isHtml($content)) {
            return new JsonResponse(['content' => $content]);
        }

        try {
            $html = (new Html($content))
                ->sanitize($pasteConfig['sanitize'] ?? [])
                ->prettyPrint($pasteConfig['prettyPrint'] ?? []);

            return new JsonResponse(['content' => (string) $html]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['noFlash' => true]);

            return new JsonResponse(['content' => $content]);
        }
    }
}

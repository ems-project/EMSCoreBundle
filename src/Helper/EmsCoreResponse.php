<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class EmsCoreResponse
{
    /**
     * @param mixed[] $body
     */
    public static function createJsonResponse(Request $request, bool $success, array $body = []): JsonResponse
    {
        $body['success'] = $success;
        $body['acknowledged'] = true;

        if (!$request->hasSession()) {
            return new JsonResponse($body);
        }

        /** @var Session $session */
        $session = $request->getSession();
        $bag = $session->getFlashBag();
        foreach (['notice', 'warning', 'error'] as $level) {
            $messages = $bag->get($level);
            if (!empty($messages)) {
                $body[$level] = $messages;
            }
        }

        return new JsonResponse($body);
    }
}

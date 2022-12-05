<?php

namespace EMS\CoreBundle\Helper;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

        $bag = $request->getSession()->getFlashBag();
        foreach (['notice', 'warning', 'error'] as $level) {
            $messages = $bag->get($level);
            if (!empty($messages)) {
                $body[$level] = $messages;
            }
        }

        return new JsonResponse($body);
    }
}

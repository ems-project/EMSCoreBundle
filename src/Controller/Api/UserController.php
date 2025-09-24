<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
    }

    public function proxyAuthenticate(Request $request): JsonResponse
    {
        $data = Json::decode($request->getContent());

        if (!isset($data['username']) || !isset($data['email'])) {
            throw new BadRequestException('Missing "username" or "email" parameter');
        }

        return new JsonResponse([
            'success' => true,
            'token' => $this->userManager->proxyAuthenticate($data['username'], $data['email']),
        ]);
    }
}

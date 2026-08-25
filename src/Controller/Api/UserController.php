<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly GroupManager $groupManager,
    ) {
    }

    public function proxyAuthenticate(Request $request): JsonResponse
    {
        $data = Json::decode($request->getContent());

        if (!isset($data['username'], $data['email'])) {
            throw new BadRequestException('Missing "username" or "email" parameter');
        }

        $group = isset($data['group']) ? $this->groupManager->getByItemName($data['group']) : null;

        try {
            return new JsonResponse([
                'success' => true,
                'token' => $this->userManager->proxyAuthenticate(
                    username: $data['username'],
                    email: $data['email'],
                    group: $group
                ),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => [$e->getMessage()]]);
        }
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\UserService;
use Mcp\Server\Builder;
use Psr\Log\LoggerInterface;

final readonly class ElasticmsMcpToolUserService
{
    use ElasticmsMcpToolCallTrait;

    public function __construct(
        private UserService $userService,
        private LoggerInterface $logger,
        private LoggerInterface $auditLogger,
    ) {
    }

    public function addUserTools(Builder $builder): void
    {
        $builder->addTool(
            handler: $this->getCurrentUser(...),
            name: 'get_current_user',
            description: 'Return the authenticated elasticMS user profile, including roles, circles, locale preferences and user options. Use this tool to check which identity and permissions the MCP calls run with.',
            inputSchema: [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => [],
                'additionalProperties' => false,
            ],
            outputSchema: [
                'type' => 'object',
                'properties' => [
                    'user' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => ['type' => 'string'],
                            'displayName' => ['type' => 'string'],
                            'roles' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'email' => ['type' => 'string'],
                            'circles' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'lastLogin' => ['type' => [
                                'anyOf' => [[
                                    'type' => 'string',
                                ], [
                                    'type' => 'null',
                                ]],
                            ]],
                            'expirationDate' => ['type' => [
                                'anyOf' => [[
                                    'type' => 'string',
                                ], [
                                    'type' => 'null',
                                ]],
                            ]],
                            'locale' => ['type' => 'string'],
                            'localePreferred' => ['type' => [
                                'anyOf' => [[
                                    'type' => 'string',
                                ], [
                                    'type' => 'null',
                                ]],
                            ]],
                        ],
                        'required' => ['username', 'displayName', 'roles', 'email', 'circles', 'lastLogin', 'expirationDate', 'locale', 'localePreferred'],
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['user'],
                'additionalProperties' => false,
            ],
        );
    }

    /**
     * @return array{user: array<mixed>}
     */
    private function getCurrentUser(): array
    {
        $currentUser = $this->userService->getCurrentUser();
        if (!$currentUser instanceof User) {
            throw new \RuntimeException('Current user is not an Elasticms user.');
        }

        return $this->wrapToolCall('get_current_user', [], fn (): array => [
            'user' => [
                'username' => $currentUser->getUsername(),
                'displayName' => $currentUser->getDisplayName(),
                'roles' => $currentUser->getRoles(),
                'email' => $currentUser->getEmail(),
                'circles' => $currentUser->getCircles(),
                'lastLogin' => $currentUser->getLastLogin() ? $currentUser->getLastLogin()->format('c') : null,
                'expirationDate' => $currentUser->getExpirationDate() ? $currentUser->getExpirationDate()->format('c') : null,
                'locale' => $currentUser->getLocale(),
                'localePreferred' => $currentUser->getLocalePreferred(),
            ],
        ]);
    }
}

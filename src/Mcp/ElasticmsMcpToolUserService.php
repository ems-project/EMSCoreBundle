<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;

final readonly class ElasticmsMcpToolUserService extends AbstractElasticmsMcpToolService
{
    public function __construct(
        UserService $userService,
        LoggerInterface $logger,
        LoggerInterface $auditLogger,
    ) {
        parent::__construct($userService, $logger, $auditLogger);
    }

    /**
     * @return array{user: array<mixed>}
     */
    public function getCurrentUser(): array
    {
        return $this->wrapToolCall('get_current_user', [], fn (): array => [
            'user' => $this->userService->getCurrentUser()->toArray(),
        ]);
    }
}

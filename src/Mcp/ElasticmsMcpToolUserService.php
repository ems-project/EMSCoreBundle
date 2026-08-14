<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Mcp;

final readonly class ElasticmsMcpToolUserService extends AbstractElasticmsMcpToolService
{
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

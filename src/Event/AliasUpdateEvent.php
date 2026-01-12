<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class AliasUpdateEvent extends Event
{
    final public const string NAME = 'ems_core.alias.update';

    /**
     * @param array<mixed> $actions
     */
    public function __construct(private readonly string $alias, private readonly array $actions)
    {
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return array<mixed>
     */
    public function getActions(): array
    {
        return $this->actions;
    }
}

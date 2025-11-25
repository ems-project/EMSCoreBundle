<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Event;

use EMS\CoreBundle\Entity\Environment;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class NewIndexEvent extends Event
{
    final public const string NAME = 'ems_core.environment.new_index';

    /**
     * @param string[] $aliases
     */
    public function __construct(private readonly Environment $environment, private readonly string $index, private readonly array $aliases, private readonly ?string $oldIndex)
    {
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getOldIndex(): ?string
    {
        return $this->oldIndex;
    }
}

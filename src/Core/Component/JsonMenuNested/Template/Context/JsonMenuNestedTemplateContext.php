<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Template\Context;

class JsonMenuNestedTemplateContext
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(public array $raw = [])
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function append(array $context): void
    {
        $this->raw = [...$this->raw, ...$context];
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

final class TransformContext
{
    /** @var mixed */
    private $transformed;

    /**
     * @param array<mixed> $options
     */
    public function __construct(private readonly mixed $data, private readonly array $options)
    {
        $this->transformed = $data;
    }

    public function isTransformed(): bool
    {
        return $this->data !== $this->transformed;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array<mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return mixed
     */
    public function getTransformed()
    {
        return $this->transformed;
    }

    public function setTransformed(mixed $transformed): void
    {
        $this->transformed = $transformed;
    }
}

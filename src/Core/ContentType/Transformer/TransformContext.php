<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

final class TransformContext
{
    /** @var mixed */
    private $data;
    /** @var array<mixed> */
    private array $options;
    /** @var mixed */
    private $transformed;

    /**
     * @param mixed        $data
     * @param array<mixed> $options
     */
    public function __construct($data, array $options)
    {
        $this->data = $data;
        $this->transformed = $data;
        $this->options = $options;
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

    /**
     * @param mixed $transformed
     */
    public function setTransformed($transformed): void
    {
        $this->transformed = $transformed;
    }
}

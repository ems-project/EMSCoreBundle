<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

use EMS\CoreBundle\Form\View\ViewType;

final readonly class ViewTypes
{
    /** @var array<string, ViewType> */
    private array $viewTypes;

    /**
     * @param \Traversable<ViewType> $viewTypes
     */
    public function __construct(\Traversable $viewTypes)
    {
        $this->viewTypes = \iterator_to_array($viewTypes);
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return \array_keys($this->viewTypes);
    }

    public function get(string $id): ViewType
    {
        return $this->viewTypes[$id];
    }
}

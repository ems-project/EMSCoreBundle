<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * @implements \IteratorAggregate<TableItemAction|TableItemActionCollection>
 */
final class TableItemActionCollection implements \IteratorAggregate, \Countable
{
    /** @var array<int, TableItemAction|TableItemActionCollection>
     */
    private array $itemActions = [];

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string|TranslatableMessage|null $label = null,
        public ?string $icon = null,
        private readonly array $attributes = [],
    ) {
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->itemActions);
    }

    /** @return array <string, string> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->itemActions);
    }

    public function addItemActionCollection(TableItemActionCollection $itemActionCollection): void
    {
        $this->itemActions[] = $itemActionCollection;
    }

    /**
     * @param array<mixed>          $routeParameters
     * @param array<string, string> $attributes
     */
    public function addItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = [], array $attributes = []): TableItemAction
    {
        $action = TableItemAction::getAction($route, $labelKey, $icon, $routeParameters, $attributes);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, mixed>  $routeParameters
     * @param array<string, string> $attributes
     */
    public function addItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey = null, array $routeParameters = [], array $attributes = []): TableItemAction
    {
        $action = TableItemAction::postAction($route, $labelKey, $icon, $messageKey, $routeParameters, $attributes);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string|int> $routeParameters
     * @param array<string, string>     $attributes
     */
    public function addDynamicItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey = null, array $routeParameters = [], array $attributes = []): TableItemAction
    {
        $action = TableItemAction::postDynamicAction($route, $labelKey, $icon, $messageKey, $routeParameters, $attributes);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string> $routeParameters
     * @param array<string, string> $attributes
     */
    public function addDynamicItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = [], array $attributes = []): TableItemAction
    {
        $action = TableItemAction::getDynamicAction($route, $labelKey, $icon, $routeParameters, $attributes);
        $this->itemActions[] = $action;

        return $action;
    }
}

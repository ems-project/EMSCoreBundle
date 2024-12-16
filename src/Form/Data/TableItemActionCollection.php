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

    public function __construct(
        public string|TranslatableMessage|null $label = null,
        public ?string $icon = null,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->itemActions);
    }

    public function count(): int
    {
        return \count($this->itemActions);
    }

    public function addItemActionCollection(TableItemActionCollection $itemActionCollection): void
    {
        $this->itemActions[] = $itemActionCollection;
    }

    /**
     * @param array<mixed> $routeParameters
     */
    public function addItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::getAction($route, $labelKey, $icon, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public function addItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey = null, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::postAction($route, $labelKey, $icon, $messageKey, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string|int> $routeParameters
     */
    public function addDynamicItemPostAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey = null, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::postDynamicAction($route, $labelKey, $icon, $messageKey, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public function addDynamicItemGetAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $action = TableItemAction::getDynamicAction($route, $labelKey, $icon, $routeParameters);
        $this->itemActions[] = $action;

        return $action;
    }
}

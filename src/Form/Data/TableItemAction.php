<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Data\Condition\ConditionInterface;
use Symfony\Component\Translation\TranslatableMessage;

final class TableItemAction
{
    private string $buttonType = 'default';
    /** @var ConditionInterface[] */
    private array $conditions = [];

    /**
     * @param array<string, mixed> $routeParameters
     */
    private function __construct(
        private readonly bool $post,
        private readonly string $route,
        private readonly TranslatableMessage $labelKey,
        private readonly string $icon,
        private readonly ?TranslatableMessage $messageKey,
        private readonly array $routeParameters,
        private bool $dynamic = false,
    ) {
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function postAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey, array $routeParameters = []): TableItemAction
    {
        $labelKey = $labelKey instanceof TranslatableMessage ? $labelKey : new TranslatableMessage($labelKey, [], EMSCoreBundle::TRANS_DOMAIN);
        if (null !== $messageKey) {
            $messageKey = $messageKey instanceof TranslatableMessage ? $messageKey : new TranslatableMessage($messageKey, [], EMSCoreBundle::TRANS_DOMAIN);
        }

        return new self(true, $route, $labelKey, $icon, $messageKey, $routeParameters);
    }

    /**
     * @param array<string, mixed> $routeParameters
     */
    public static function getAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $labelKey = $labelKey instanceof TranslatableMessage ? $labelKey : new TranslatableMessage($labelKey, [], EMSCoreBundle::TRANS_DOMAIN);

        return new self(false, $route, $labelKey, $icon, null, $routeParameters);
    }

    /**
     * @param array<string, string|int> $routeParameters
     */
    public static function postDynamicAction(string $route, string|TranslatableMessage $labelKey, string $icon, string|TranslatableMessage|null $messageKey, array $routeParameters = []): TableItemAction
    {
        $labelKey = $labelKey instanceof TranslatableMessage ? $labelKey : new TranslatableMessage($labelKey, [], EMSCoreBundle::TRANS_DOMAIN);
        if (null !== $messageKey) {
            $messageKey = $messageKey instanceof TranslatableMessage ? $messageKey : new TranslatableMessage($messageKey, [], EMSCoreBundle::TRANS_DOMAIN);
        }

        return new self(true, $route, $labelKey, $icon, $messageKey, $routeParameters, true);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    public static function getDynamicAction(string $route, string|TranslatableMessage $labelKey, string $icon, array $routeParameters = []): TableItemAction
    {
        $labelKey = $labelKey instanceof TranslatableMessage ? $labelKey : new TranslatableMessage($labelKey, [], EMSCoreBundle::TRANS_DOMAIN);

        return new self(false, $route, $labelKey, $icon, null, $routeParameters, true);
    }

    public function isPost(): bool
    {
        return $this->post;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getLabelKey(?string $itemLabel = null): TranslatableMessage
    {
        return new TranslatableMessage(
            message: $this->labelKey->getMessage(),
            parameters: [...$this->labelKey->getParameters(), ...['%label%' => $itemLabel]],
            domain: $this->labelKey->getDomain()
        );
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getMessageKey(?string $itemLabel = null): ?TranslatableMessage
    {
        if (null === $this->messageKey) {
            return null;
        }

        return new TranslatableMessage(
            message: $this->messageKey->getMessage(),
            parameters: [...$this->messageKey->getParameters(), ...['%label%' => $itemLabel]],
            domain: $this->messageKey->getDomain()
        );
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function setDynamic(bool $dynamic): TableItemAction
    {
        $this->dynamic = $dynamic;

        return $this;
    }

    public function setButtonType(string $buttonType): void
    {
        $this->buttonType = $buttonType;
    }

    public function getButtonType(): string
    {
        return $this->buttonType;
    }

    public function addCondition(ConditionInterface $condition): TableItemAction
    {
        $this->conditions[] = $condition;

        return $this;
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function valid($objectOrArray): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->valid($objectOrArray)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Translation\TranslatableMessage;

final class TableAction
{
    private string $cssClass = 'btn btn-default';
    private ?string $routeName = null;
    /** @var array<mixed> */
    private array $routeParams = [];

    public function __construct(
        private readonly string $name,
        private readonly string $icon,
        private readonly TranslatableMessage $labelKey,
        private readonly ?TranslatableMessage $confirmationKey = null)
    {
    }

    public static function create(
        string $name,
        string $icon,
        string|TranslatableMessage $labelKey,
        string|TranslatableMessage|null $confirmationKey = null,
    ): self {
        if (!$labelKey instanceof TranslatableMessage) {
            $labelKey = new TranslatableMessage($labelKey, [], EMSCoreBundle::TRANS_DOMAIN);
        }
        if (null !== $confirmationKey && !$confirmationKey instanceof TranslatableMessage) {
            $confirmationKey = new TranslatableMessage($confirmationKey, [], EMSCoreBundle::TRANS_DOMAIN);
        }

        return new self($name, $icon, $labelKey, $confirmationKey);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabelKey(): TranslatableMessage
    {
        return $this->labelKey;
    }

    public function getConfirmationKey(): ?TranslatableMessage
    {
        return $this->confirmationKey;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getCssClass(): string
    {
        return $this->cssClass;
    }

    public function setCssClass(string $cssClass): void
    {
        $this->cssClass = $cssClass;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    /**
     * @return array<string, string>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function setRoute(?string $routeName, array $routeParams = []): void
    {
        $this->routeName = $routeName;
        $this->routeParams = $routeParams;
    }
}

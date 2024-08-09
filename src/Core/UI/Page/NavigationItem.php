<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI\Page;

use Symfony\Component\Translation\TranslatableMessage;

class NavigationItem
{
    /**
     * @param array<mixed> $routeParams
     */
    public function __construct(
        public readonly ?TranslatableMessage $label = null,
        public readonly ?string $text = null,
        public readonly ?string $icon = null,
        public readonly ?string $route = null,
        public readonly array $routeParams = [],
    ) {
    }
}

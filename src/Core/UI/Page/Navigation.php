<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI\Page;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Routes;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class Navigation
{
    /** @var NavigationItem[] */
    public array $items = [];

    public static function admin(): self
    {
        return (new self())->add(label: t('key.admin', [], 'emsco-core'));
    }

    public function contentType(ContentType $contentType): self
    {
        $this->contentTypes();

        return $this->add(
            text: $contentType->getSingularName(),
            icon: $contentType->getIcon(),
            route: Routes::ADMIN_CONTENT_TYPE_EDIT,
            routeParams: ['contentType' => $contentType->getId()]
        );
    }

    public function contentTypeActions(ContentType $contentType): self
    {
        return $this->add(
            label: t('key.actions', [], 'emsco-core'),
            icon: 'fa fa-gear',
            route: Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX,
            routeParams: ['contentType' => $contentType->getId()]
        );
    }

    public function contentTypeViews(ContentType $contentType): self
    {
        return $this->add(
            label: t('key.views', [], 'emsco-core'),
            icon: 'fa fa-tv',
            route: Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX,
            routeParams: ['contentType' => $contentType->getId()]
        );
    }

    public function contentTypes(): self
    {
        return $this->add(
            label: t('key.content_types', [], 'emsco-core'),
            icon: 'fa fa-sitemap',
            route: Routes::ADMIN_CONTENT_TYPE_INDEX
        );
    }

    /**
     * @param array<mixed> $routeParams
     */
    public function add(
        ?TranslatableMessage $label = null,
        ?string $text = null,
        ?string $icon = null,
        ?string $route = null,
        array $routeParams = [],
    ): self {
        $this->items[] = new NavigationItem(
            label: $label,
            text: $text,
            icon: $icon,
            route: $route,
            routeParams: $routeParams
        );

        return $this;
    }
}

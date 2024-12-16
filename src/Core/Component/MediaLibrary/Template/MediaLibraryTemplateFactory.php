<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Template;

use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use Twig\Environment;

class MediaLibraryTemplateFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $templateNamespace,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function create(MediaLibraryConfig $config, array $context = []): MediaLibraryTemplate
    {
        return new MediaLibraryTemplate($this->twig, $config, $this->templateNamespace, $context);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use Twig\Environment;

class MediaLibraryTemplateFactory
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $templateNamespace
    ) {
    }

    public function create(MediaLibraryConfig $config): MediaLibraryTemplate
    {
        return new MediaLibraryTemplate($this->twig, $config, $this->templateNamespace);
    }
}

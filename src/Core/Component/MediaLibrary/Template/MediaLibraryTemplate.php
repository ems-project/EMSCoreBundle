<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Template;

use EMS\CoreBundle\Core\Component\AbstractComponentTemplate;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use Twig\Environment;

class MediaLibraryTemplate extends AbstractComponentTemplate
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        Environment $twig,
        MediaLibraryConfig $config,
        string $templateNamespace,
        array $context = [],
    ) {
        parent::__construct(
            $twig,
            "@$templateNamespace/components/media_library/template.twig",
            $config->template
        );

        $this->context->append([
            ...['config' => $config, 'id' => $config->getId()],
            ...$config->context,
            ...$context,
        ]);
    }
}

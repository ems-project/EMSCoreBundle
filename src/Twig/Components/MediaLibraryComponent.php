<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components;

use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\Config\MediaLibraryConfigFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\Template\MediaLibraryTemplate;
use EMS\CoreBundle\Core\Component\MediaLibrary\Template\MediaLibraryTemplateFactory;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;

final class MediaLibraryComponent
{
    public function __construct(
        private readonly MediaLibraryConfigFactory $mediaLibraryConfigFactory,
        private readonly MediaLibraryTemplateFactory $templateFactory,
    ) {
    }

    #[ExposeInTemplate('hash')]
    public string $hash;
    #[ExposeInTemplate('id')]
    public string $id;
    #[ExposeInTemplate('config')]
    public MediaLibraryConfig $config;
    #[ExposeInTemplate('template')]
    public MediaLibraryTemplate $template;

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    #[PreMount]
    public function validate(array $options): array
    {
        /** @var MediaLibraryConfig $config */
        $config = $this->mediaLibraryConfigFactory->createFromOptions($options);

        $this->hash = $config->getHash();
        $this->id = $config->getId();
        $this->config = $config;
        $this->template = $this->templateFactory->create($config);

        return [];
    }
}

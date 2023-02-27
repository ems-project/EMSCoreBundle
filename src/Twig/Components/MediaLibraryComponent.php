<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components;

use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfig;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfigFactory;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryTemplate;
use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryTemplateFactory;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;

final class MediaLibraryComponent
{
    public function __construct(
        private readonly MediaLibraryConfigFactory $mediaLibraryConfigFactory,
        private readonly MediaLibraryTemplateFactory $templateFactory
    ) {
    }

    #[ExposeInTemplate('hash')]
    public string $hash;
    #[ExposeInTemplate('id')]
    public string $id;
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
        $template = $this->templateFactory->create($config);

        $this->hash = $config->getHash();
        $this->id = $config->getId();
        $this->template = $template;

        return [];
    }
}

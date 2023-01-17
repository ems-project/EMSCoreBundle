<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components;

use EMS\CoreBundle\Core\Component\MediaLibrary\MediaLibraryConfigFactory;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;

final class MediaLibraryComponent
{
    public function __construct(
        private readonly MediaLibraryConfigFactory $mediaLibraryConfigFactory
    ) {
    }

    #[ExposeInTemplate('hash')]
    public string $hash;
    #[ExposeInTemplate('id')]
    public string $id;

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    #[PreMount]
    public function validate(array $options): array
    {
        $config = $this->mediaLibraryConfigFactory->createFromOptions($options);

        $this->hash = $config->getHash();
        $this->id = $config->getId();

        return [];
    }
}

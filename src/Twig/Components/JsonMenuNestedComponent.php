<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig\Components;

use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfig;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Config\JsonMenuNestedConfigFactory;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplate;
use EMS\CoreBundle\Core\Component\JsonMenuNested\Template\JsonMenuNestedTemplateFactory;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;

class JsonMenuNestedComponent
{
    public function __construct(
        private readonly JsonMenuNestedConfigFactory $jsonMenuNestedConfigFactory,
        private readonly JsonMenuNestedTemplateFactory $jsonMenuNestedTemplateFactory,
    ) {
    }

    #[ExposeInTemplate('hash')]
    public string $hash;
    #[ExposeInTemplate('id')]
    public string $id;
    #[ExposeInTemplate('config')]
    public JsonMenuNestedConfig $config;
    #[ExposeInTemplate('template')]
    public JsonMenuNestedTemplate $template;

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    #[PreMount]
    public function validate(array $options): array
    {
        /** @var JsonMenuNestedConfig $config */
        $config = $this->jsonMenuNestedConfigFactory->createFromOptions($options);

        $this->hash = $config->getHash();
        $this->id = $config->getId();
        $this->config = $config;
        $this->template = $this->jsonMenuNestedTemplateFactory->create($config);

        return [];
    }
}

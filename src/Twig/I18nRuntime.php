<?php

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Service\I18nService;
use Twig\Extension\RuntimeExtensionInterface;

class I18nRuntime implements RuntimeExtensionInterface
{
    private I18nService $i18nService;

    public function __construct(I18nService $i18nService)
    {
        $this->i18nService = $i18nService;
    }

    /**
     * @return array<string, mixed>
     */
    public function findAll(string $name, bool $jsonDecode = false): array
    {
        $i18n = $this->i18nService->getByItemName($name);

        if (null === $i18n) {
            return [];
        }

        $content = [];
        \array_map(function ($element) use ($jsonDecode, &$content) {
            if (!\is_string($element['locale'])) {
                throw new \RuntimeException('Unexpected non string locale');
            }
            $content[$element['locale']] = $jsonDecode ? Json::decode($element['text']) : $element['text'];
        }, $i18n->getContent());

        return $content;
    }
}

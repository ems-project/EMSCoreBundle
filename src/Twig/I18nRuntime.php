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
     * @param string $name
     * @param bool $jsonDecode
     * @return array<array>
     */
    public function findAll(string $name, bool $jsonDecode = false): array
    {
        $i18n = $this->i18nService->getByItemName($name);

        if ($i18n && $jsonDecode) {
            $decodedContent = [];
            foreach ($i18n->getContent() as $content) {
                $decodedContent[] = ['locale' => $content['locale'], 'text' => Json::decode($content['text'])];
            }
            return $decodedContent;
        }

        return $i18n ? $i18n->getContent() : [];
    }
}

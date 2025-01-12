<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\I18nService;
use EMS\Helpers\Standard\Json;
use Twig\Extension\RuntimeExtensionInterface;

class I18nRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly I18nService $i18nService,
        private readonly UserManager $userManager,
    ) {
    }

    public function i18n(string $key, ?string $locale = null): string
    {
        $i18n = $this->i18nService->getAsList($key);
        $locale ??= $this->userManager->getUserLanguage();

        return $i18n[$locale] ?? $i18n[User::DEFAULT_LOCALE] ?? $key;
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
            if ('' === $element['locale']) {
                throw new \RuntimeException('Unexpected non string locale');
            }
            $content[$element['locale']] = $jsonDecode ? Json::decode($element['text']) : $element['text'];
        }, $i18n->getContent());

        return $content;
    }
}

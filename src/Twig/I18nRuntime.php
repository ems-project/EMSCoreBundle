<?php

namespace EMS\CoreBundle\Twig;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\I18nService;
use EMS\CoreBundle\Service\UserService;
use Twig\Extension\RuntimeExtensionInterface;

class I18nRuntime implements RuntimeExtensionInterface
{
    private I18nService $i18nService;
    private string $fallbackLocale;
    private UserService $userService;

    public function __construct(I18nService $i18nService, UserService $userService, string $fallbackLocale)
    {
        $this->i18nService = $i18nService;
        $this->userService = $userService;
        $this->fallbackLocale = $fallbackLocale;
    }

    public function i18n(string $key, string $locale = null): string
    {
        if ('' === $locale || null === $locale) {
            $locale = $this->getLocale();
        }

        $i18n = $this->i18nService->getAsList($key);

        return $i18n[$locale] ?? $i18n[$this->fallbackLocale] ?? $key;
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

    private function getLocale(): string
    {
        try {
            $user = $this->userService->getCurrentUser(true);
            if ($user instanceof User) {
                return $user->getLocalePreferred() ?? $user->getLocale();
            }
        } catch (\Throwable $e) {
        }

        return $this->fallbackLocale;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\I18nService;
use EMS\Helpers\Standard\Json;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

readonly class I18nExtension
{
    public function __construct(
        private I18nService $i18nService,
        private UserManager $userManager,
    ) {
    }

    #[AsTwigFilter(name: 'emsco_i18n')]
    public function i18n(string $key, ?string $locale = null): string
    {
        $i18n = $this->i18nService->getAsList($key);
        if (null !== $locale) {
            return $i18n[$locale] ?? $key;
        }
        $locale = $this->userManager->getUserLanguage();

        return $i18n[$locale] ?? $i18n[User::DEFAULT_LOCALE] ?? $key;
    }

    /**
     * @return array<string, mixed>
     */
    #[AsTwigFunction(name: 'emsco_i18n_all')]
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

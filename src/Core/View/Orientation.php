<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\View;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum Orientation: string implements TranslatableInterface
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::Portrait => t('orientation.portrait', [], 'emsco-core')->trans($translator),
            self::Landscape => t('orientation.landscape', [], 'emsco-core')->trans($translator),
        };
    }
}

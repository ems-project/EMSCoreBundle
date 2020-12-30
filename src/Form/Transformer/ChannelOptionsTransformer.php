<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

final class ChannelOptionsTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        $locales = $value['locales'] ?? [];
        if (\is_array($locales)) {
            $locales = \implode(PHP_EOL, $locales);
        }

        $searchConfig = \json_decode($value['searchConfig'] ?? '', true);
        if (null === $searchConfig) {
            $searchConfig = $value['searchConfig'] ?? '';
        } else {
            $searchConfig = \json_encode($searchConfig ?? '', JSON_PRETTY_PRINT);
        }

        return [
            'locales' => $locales,
            'searchConfig' => $searchConfig,
        ];
    }

    public function reverseTransform($value)
    {
        $locales = $value['locales'];
        if (!\is_string($locales)) {
            throw new \RuntimeException('Unexpected locales');
        }
        $locales = \preg_split("/\r\n|\n|\r/", $locales);

        return [
            'locales' => $locales,
            'searchConfig' => $value['searchConfig'] ?? '',
        ];
    }
}

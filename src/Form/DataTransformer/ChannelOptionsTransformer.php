<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

final class ChannelOptionsTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        $searchConfig = \json_decode($value['searchConfig'] ?? '', true);
        if (null === $searchConfig) {
            $searchConfig = $value['searchConfig'] ?? '';
        } else {
            $searchConfig = \json_encode($searchConfig ?? '', JSON_PRETTY_PRINT);
        }

        return [
            'searchConfig' => $searchConfig,
        ];
    }

    public function reverseTransform($value)
    {
        return [
            'searchConfig' => $value['searchConfig'] ?? '',
        ];
    }
}

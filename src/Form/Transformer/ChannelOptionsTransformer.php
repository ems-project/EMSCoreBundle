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
            $locales = \implode('\n', $locales);
        }

        $instanceId = $value['instanceId'] ?? [];
        if (\is_array($instanceId)) {
            $instanceId = \implode('|', $instanceId);
        }

        $searchConfig = \json_decode($value['searchConfig'] ?? '', true);
        if (null === $searchConfig) {
            $searchConfig = $value['searchConfig'] ?? '';
        } else {
            $searchConfig = \json_encode($searchConfig ?? '', JSON_PRETTY_PRINT);
        }

        return [
            'locales' => $locales,
            'instanceId' => $instanceId,
            'environment' => $value['environment'] ?? '',
            'translationContentType' => $value['translationContentType'] ?? '',
            'routeContentType' => $value['routeContentType'] ?? '',
            'templateContentType' => $value['templateContentType'] ?? '',
            'searchConfig' => $searchConfig,
        ];
    }

    public function reverseTransform($value)
    {
        $locales = $value['locales'];
        if (!\is_string($locales)) {
            throw new \RuntimeException('Unexpected locales');
        }
        $locales = \explode('\n', $locales);

        $instanceId = $value['instanceId'];
        if (!\is_string($instanceId)) {
            throw new \RuntimeException('Unexpected instanceId');
        }
        $instanceId = \explode('|', $instanceId);

        return [
            'locales' => $locales,
            'instanceId' => $instanceId,
            'environment' => $value['environment'] ?? '',
            'translationContentType' => $value['translationContentType'] ?? '',
            'routeContentType' => $value['routeContentType'] ?? '',
            'templateContentType' => $value['templateContentType'] ?? '',
            'searchConfig' => $value['searchConfig'] ?? '',
        ];
    }
}

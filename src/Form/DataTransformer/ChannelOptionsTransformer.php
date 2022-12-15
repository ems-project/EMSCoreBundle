<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
final class ChannelOptionsTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        $searchConfig = $this->jsonFormat($value, 'searchConfig');
        $attributes = $this->jsonFormat($value, 'attributes');

        return [
            'searchConfig' => $searchConfig,
            'entryPath' => $value['entryPath'] ?? null,
            'attributes' => $attributes,
        ];
    }

    public function reverseTransform($value)
    {
        return [
            'searchConfig' => $value['searchConfig'] ?? '',
            'entryPath' => $value['entryPath'] ?? '',
            'attributes' => $value['attributes'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function jsonFormat(array $value, string $attribute): string
    {
        $defaultFormatted = (isset($value[$attribute]) && '' !== $value[$attribute]) ? $value[$attribute] : '{}';
        $formatted = \json_decode($defaultFormatted, true, 512, JSON_THROW_ON_ERROR);

        return (null !== $formatted && \json_encode($formatted, JSON_PRETTY_PRINT))
            ? \json_encode($formatted, JSON_PRETTY_PRINT)
            : '{}';
    }
}

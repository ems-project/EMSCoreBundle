<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
final class ChannelOptionsTransformer implements DataTransformerInterface
{
    #[\Override]
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

    #[\Override]
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
        return Json::prettyPrint($value[$attribute] ?? '{}');
    }
}

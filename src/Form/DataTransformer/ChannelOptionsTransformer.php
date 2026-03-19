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
    /**
     * @return array<mixed>
     */
    #[\Override]
    public function transform(mixed $value): array
    {
        $searchConfig = $this->jsonFormat($value, 'searchConfig');
        $attributes = $this->jsonFormat($value, 'attributes');

        return [
            'prefix_instance_id' => $value['prefix_instance_id'] ?? false,
            'inline_editor' => $value['inline_editor'] ?? false,
            'searchConfig' => $searchConfig,
            'entryPath' => $value['entryPath'] ?? null,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function reverseTransform(mixed $value): array
    {
        return [
            'prefix_instance_id' => $value['prefix_instance_id'] ?? '',
            'inline_editor' => $value['inline_editor'] ?? '',
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

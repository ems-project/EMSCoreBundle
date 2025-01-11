<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataTransformer;

use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<mixed, mixed>
 */
final class QuerySearchOptionsTransformer implements DataTransformerInterface
{
    /** @return array<string, mixed> */
    #[\Override]
    public function transform($value): array
    {
        $query = $this->jsonFormat($value['query']);

        return [
            'query' => $query,
        ];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function reverseTransform($value): array
    {
        return [
            'query' => $value['query'] ?? '{}',
        ];
    }

    private function jsonFormat(string $value): string
    {
        try {
            return Json::encode(Json::decode($value), true);
        } catch (\Throwable) {
            return $value;
        }
    }
}

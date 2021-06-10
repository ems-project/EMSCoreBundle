<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

interface ContentTransformerInterface
{
    public function getName(): string;

    public function validateConfig(string $config): ?string;

    public function supports(string $fieldTypeClass): bool;

    public function transform(TransformContext $context): void;
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

use Symfony\Component\Translation\TranslatableMessage;

interface ContentTransformerInterface
{
    public function getName(): TranslatableMessage;

    public function validateConfig(string $config): ?string;

    public function supports(string $fieldTypeClass): bool;

    public function transform(TransformContext $context): void;
}

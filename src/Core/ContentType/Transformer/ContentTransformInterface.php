<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

interface ContentTransformInterface
{
    public function canTransform(ContentTransformContext $contentTransformContext): bool;

    public function transform(ContentTransformContext $contentTransformContext): string;

    public function hasChanges(ContentTransformContext $contentTransformContext): bool;
}

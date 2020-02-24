<?php

namespace EMS\CoreBundle\ContentTransformer;

interface ContentTransformInterface
{
    public function canTransform(ContentTransformContext $contentTransformContext): bool;
    public function transform(ContentTransformContext $contentTransformContext): string;
}

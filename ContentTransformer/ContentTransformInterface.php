<?php

namespace EMS\CoreBundle\ContentTransformer;

interface ContentTransformInterface
{
    public function canTransform(ContentTransformContext $contentTransformContext): bool;
    public function transform(string $input): string;
    public function changed(string $output): bool;
}

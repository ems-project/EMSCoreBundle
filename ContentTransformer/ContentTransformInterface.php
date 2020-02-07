<?php

namespace EMS\CoreBundle\ContentTransformer;

interface ContentTransformInterface
{
    public function canTransform(): bool;
    public function transform(): void;
    public function changed(): bool;
}

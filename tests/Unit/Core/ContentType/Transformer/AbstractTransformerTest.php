<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\ContentType\Transformer;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformerInterface;
use EMS\CoreBundle\Core\ContentType\Transformer\TransformContext;
use PHPUnit\Framework\TestCase;

abstract class AbstractTransformerTest extends TestCase
{
    abstract protected function getTransformer(): ContentTransformerInterface;

    /**
     * @param mixed $output
     */
    protected function assertEqualsInputOutPut(string $input, $output, array $options = []): void
    {
        $context = new TransformContext($input, $options);
        $this->getTransformer()->transform($context);

        $this->assertSame($output, $context->getTransformed());
    }
}

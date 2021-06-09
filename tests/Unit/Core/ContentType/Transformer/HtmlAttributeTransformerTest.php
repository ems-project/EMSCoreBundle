<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\ContentType\Transformer;

use EMS\CoreBundle\Core\ContentType\Transformer\HtmlAttributeTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\TransformContext;
use PHPUnit\Framework\TestCase;

class HtmlAttributeTransformerTest extends TestCase
{
    private HtmlAttributeTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new HtmlAttributeTransformer();
    }

    private function assertEqualsInputOutPut(string $input, string $output, array $options): void
    {
        $context = new TransformContext($input, $options);
        $this->transformer->transform($context);

        $this->assertEquals($output, $context->getTransformed());
    }

    public function testRemoveOneClass(): void
    {
        $input = <<<HTML
<div><span id="title" class="bg-dark remove-style-test font-light">Cool</span></div>
HTML;
        $output = <<<HTML
<div><span id="title" class="bg-dark font-light">Cool</span></div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'class',
            'remove_value_prefix' => 'remove-style-test',
        ]);
    }

    public function testRemoveMultipleClasses(): void
    {
        $input = <<<HTML
<div><span id="title" class="bg-dark font-light font-bold">Cool</span></div>
HTML;
        $output = <<<HTML
<div><span id="title" class="bg-dark">Cool</span></div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'class',
            'remove_value_prefix' => 'font-',
        ]);
    }

    public function testNoEmptyClassAttribute(): void
    {
        $input = <<<HTML
<div><span id="test" class="bg-dark bg-red">Cool</span></div>
HTML;
        $output = <<<HTML
<div><span id="test">Cool</span></div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'class',
            'remove_value_prefix' => 'bg-',
        ]);
    }

    public function testOnlySpanClassRemove(): void
    {
        $input = <<<HTML
<div class="container bg-dark">
    <div class="test">
        <h1>Test</h1>
        <span class="bg-dark test"></span>
    </div>
</div>
HTML;
        $output = <<<HTML
<div class="container bg-dark">
    <div class="test">
        <h1>Test</h1>
        <span class="test"></span>
    </div>
</div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'class',
            'element' => 'span',
            'remove_value_prefix' => 'bg-dark',
        ]);
    }

    public function testRemoveStyle(): void
    {
        $input = <<<HTML
<div class="test">
    <h1>Test</h1>
    <span style="background: red; font-size: 11px; color: blue;"></span>
</div>
HTML;
        $output = <<<HTML
<div class="test">
    <h1>Test</h1>
    <span style="background: red; color: blue;"></span>
</div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'style',
            'element' => 'span',
            'remove_value_prefix' => 'font-',
        ]);
    }

    public function testNoEmptyStyleAttribute(): void
    {
        $input = <<<HTML
<div class="test">
    <h1>Test</h1>
    <span style="background: red;"></span>
</div>
HTML;
        $output = <<<HTML
<div class="test">
    <h1>Test</h1>
    <span></span>
</div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'style',
            'element' => 'span',
            'remove_value_prefix' => 'background',
        ]);
    }

    public function testClearTableStyles(): void
    {
        $input = <<<HTML
<div id="table-test">
    <h1 style="font-size: 18px;">Table TEST</h1>
    <table style="background: blue; font-size: 12px;">
        <tr>
            <td>Col1</td>
            <td>Col2</td>
        </tr>
    </table>
</div>
HTML;
        $output = <<<HTML
<div id="table-test">
    <h1 style="font-size: 18px;">Table TEST</h1>
    <table>
        <tr>
            <td>Col1</td>
            <td>Col2</td>
        </tr>
    </table>
</div>
HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'attribute' => 'style',
            'element' => 'table',
            'clear' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\ContentType\Transformer;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformerInterface;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlUnwrapTransformer;

class HtmlUnwrapTransformerTest extends AbstractTransformerTestCase
{
    #[\Override]
    protected function getTransformer(): ContentTransformerInterface
    {
        return new HtmlUnwrapTransformer();
    }

    public function testUnwrapDiv(): void
    {
        $input = '<div>Hello <strong>world</strong></div>';
        $output = 'Hello <strong>world</strong>';

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['div', 'span', 'ins'],
        ]);
    }

    public function testUnwrapSpan(): void
    {
        $input = '<p>Test <span>new word</span></p>';
        $output = '<p>Test new word</p>';

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['div', 'span', 'ins'],
        ]);
    }

    public function testUnwrapIns(): void
    {
        $input = '<p>Test <ins>new word</ins></p>';
        $output = '<p>Test new word</p>';

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['div', 'span', 'ins'],
        ]);
    }

    public function testKeepElementWithAttributes(): void
    {
        $input = '<p>Test <span class="highlight">word</span></p>';
        $output = '<p>Test <span class="highlight">word</span></p>';

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['div', 'span', 'ins'],
        ]);
    }

    public function testOnlyConfiguredElements(): void
    {
        $input = '<p>Test <span>a</span> <ins>b</ins></p>';
        $output = '<p>Test a <ins>b</ins></p>';

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['span'],
        ]);
    }

    public function testUnwrapKeepsIndentation(): void
    {
        $input = <<<HTML
            <section>
                <div>
                    <h1>Title</h1>
                    <div>
                        <p>Paragraph <span>Test</span></p>
                    </div>
                </div>
            </section>
            HTML;
        $output = <<<HTML
            <section>
                <h1>Title</h1>
                <p>Paragraph Test</p>
            </section>
            HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'elements' => ['div', 'span'],
        ]);
    }
}

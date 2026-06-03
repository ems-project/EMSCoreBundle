<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\ContentType\Transformer;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformerInterface;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlRemoveNodeTransformer;

final class HtmlRemoveNodeTransformerTest extends AbstractTransformerTestCase
{
    #[\Override]
    protected function getTransformer(): ContentTransformerInterface
    {
        return new HtmlRemoveNodeTransformer();
    }

    public function testRemoveSpan()
    {
        $input = '<p> This is a test <span>error</span> ok </p><div>Nested <span>TEST</span></div>';
        $output = '<p> This is a test  ok </p><div>Nested </div>';

        $this->assertEqualsInputOutPut($input, $output, [
            'element' => 'span',
        ]);
    }

    public function testRemoveEmptyHtml(): void
    {
        $input = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
                <body class="page">
                    <h1>Test</h1>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    <div class="deletedContent">
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    </div>
                    <p>Donec scelerisque vulputate congue. Ut tortor libero, pellentesque at porttitor sollicitudin, aliquam vel tortor.</p>
                    <div class="test-wrapper">
                        <div class="deletedContent">
                            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                        </div>
                    </div>
                </body>
            </html>
            HTML;

        $output = <<<HTML
            <!DOCTYPE html>
            <html lang="en"><body class="page">
                    <h1>Test</h1>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    
                    <p>Donec scelerisque vulputate congue. Ut tortor libero, pellentesque at porttitor sollicitudin, aliquam vel tortor.</p>
                </body>
            </html>
            HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'element' => 'div',
            'attribute' => 'class',
            'attribute_contains' => 'deletedContent',
        ]);
    }

    public function testRemoveClassContainingWord(): void
    {
        $input = <<<HTML
            <p class="deletedWord">Remove me</p>
            <p class="word deletedWord extra">Also remove</p>
            <p class="other">Keep me</p>
            HTML;

        $output = <<<HTML
            

            <p class="other">Keep me</p>
            HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'element' => 'p',
            'attribute' => 'class',
            'attribute_contains' => 'deletedWord',
        ]);
    }

    public function testRemoveDelAndEmptyListItem(): void
    {
        $input = <<<HTML
            <ul>
                <li><del class="deletedWord">Remove me</del>Keep me</li>
                <li>Keep me too</li>
                <li><del class="deletedWord">Remove full</del></li>
            </ul>
            HTML;

        $output = <<<HTML
            <ul>
                <li>Keep me</li>
                <li>Keep me too</li>
            </ul>
            HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'element' => 'del',
            'attribute' => 'class',
            'attribute_contains' => 'deletedWord',
        ]);
    }
}

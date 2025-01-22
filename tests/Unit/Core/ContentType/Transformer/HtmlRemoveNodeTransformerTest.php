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
                    <div class="test-wrapper">
                        
                    </div>
                </body>
            </html>
            HTML;

        $this->assertEqualsInputOutPut($input, $output, [
            'element' => 'div',
            'attribute' => 'class',
            'attribute_contains' => 'deletedContent',
        ]);
    }
}

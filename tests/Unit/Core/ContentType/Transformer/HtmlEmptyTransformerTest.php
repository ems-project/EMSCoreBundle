<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\ContentType\Transformer;

use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformerInterface;
use EMS\CoreBundle\Core\ContentType\Transformer\HtmlEmptyTransformer;

final class HtmlEmptyTransformerTest extends AbstractTransformerTestCase
{
    #[\Override]
    protected function getTransformer(): ContentTransformerInterface
    {
        return new HtmlEmptyTransformer();
    }

    public function testRemoveEmptyHtml(): void
    {
        $input = <<<HTML
            <p style="text-align: justify;"> </p> <div class="readMoreContent" style="text-align: justify;"> </div> <p> </p>
            HTML;

        $this->assertEqualsInputOutPut($input, '');
    }

    public function testRemoveEmptyWithEntities(): void
    {
        $input = <<<HTML
                   <p style="text-align: justify;">&nbsp;</p>
                   <div class="readMoreContent" style="text-align: justify;">&nbsp;
                       <div class="hideContent" style="display: none;">&nbsp;</div>
                   </div>
            HTML;

        $this->assertEqualsInputOutPut($input, '');
    }

    public function testRemoveSpaces(): void
    {
        $input = <<<HTML
                   &nbsp;
                       &nbsp;

                           &nbsp;
            HTML;

        $this->assertEqualsInputOutPut($input, '');
    }

    public function testRemoveHtml(): void
    {
        $input = <<<HTML
            <html>
                <body>
                        <h1>            </h1>
                        <p>&nbsp;       </p>
                </body>
            </html>
            HTML;

        $this->assertEqualsInputOutPut($input, '');
    }

    public function testKeepNotEmpty(): void
    {
        $input = <<<HTML
            <p style="text-align: justify;"> </p>
            <div class="readMoreContent" style="text-align: justify;"> TEST TEST </div>
            <p> </p>
            HTML;
        $this->assertEqualsInputOutPut($input, $input);
    }

    public function testKeepNotEmptyHtml(): void
    {
        $input = <<<HTML
            <html>
                <body>
                        <h1>Test content</h1>
                        <p>&nbsp;       </p>
                </body>
            </html>
            HTML;

        $this->assertEqualsInputOutPut($input, $input);
    }
}

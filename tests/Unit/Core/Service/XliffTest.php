<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Service;

use EMS\CoreBundle\Service\XliffService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class XliffTest extends KernelTestCase
{
    /**
     * @var string[]
     */
    private array $htmlSources = ['<html>
 <body>
  <h1 class="title">Report</h1>
  <table border="1" width="100%">
   <tr>
    <td valign="top">Text in cell r1-c1</td>
    <td valign="top">Text in cell r1-c2</td>
   </tr>
   <tr>
    <td bgcolor="#C0C0C0">Text in cell r2-c1</td>
    <td>Text in cell r2-c2</td>
   </tr>
  </table>
  <p>All rights reserved (c) Gandalf Inc.</p>
 </body>
</html>',
    ];

    /**
     * @var string[]
     */
    private array $htmlResults = [
        '<body><group restype="x-html-html"><group restype="x-html-body"><trans-unit id="1" restype="x-html-h1" html:class="title"><source xml:lang="en">Report</source></trans-unit><group restype="table" html:border="1" html:width="100%"><group restype="row"><trans-unit id="2" restype="cell" html:valign="top"><source xml:lang="en">Text in cell r1-c1</source></trans-unit><trans-unit id="3" restype="cell" html:valign="top"><source xml:lang="en">Text in cell r1-c2</source></trans-unit></group><group restype="row"><trans-unit id="4" restype="cell" html:bgcolor="#C0C0C0"><source xml:lang="en">Text in cell r2-c1</source></trans-unit><trans-unit id="5" restype="cell"><source xml:lang="en">Text in cell r2-c2</source></trans-unit></group></group><trans-unit id="6" restype="x-html-p"><source xml:lang="en">All rights reserved (c) Gandalf Inc.</source></trans-unit></group></group></body>',
    ];

    private XliffService $xliffService;

    protected function setUp(): void
    {
        $this->xliffService = new XliffService();
    }

    public function testXliffExport(): void
    {
        foreach ($this->htmlSources as $htmlSource) {
            $node = new \SimpleXMLElement('<body/>');
            $this->xliffService->htmlNode($node, $htmlSource, '', 'en', 'fr');
            $xml = \explode("\n", $node->saveXML());
            $this->assertArrayHasKey(1, $xml);
            $this->assertEquals($xml[1], $this->htmlResults[0]);
        }
    }
}

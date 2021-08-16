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
    private array $htmlSources = ['
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
  <p>All rights reserved (c) Gandalf Inc.</p>',
        '',
        '<p>Hello</p>',
        '<p>Hello Mathieu, please visits<a href="https://elasticms.eu"> <i class="elasticms"></i>The elastic<b>ms</b> web site</a></p>',
        '<p title="Information about Mount Hood">This is Mount Hood: <img src="mthood.jpg" alt="Mount Hood with its snow-covered top"></p>',
    ];

    private array $htmlTarget = [
        '',
        '<p>Bonjour</p>',
        '<p>Bonjour</p>',
        '<p>Bonjour Mathieu <a href="https://elasticms.eu">Le site d&acute;elasticms</a></p>',
        '<p title="Information à propos de Mount Hood">Ceci est Mount Hood: <img src="mthood.jpg" alt="Mount Hood avec son sommet enneigé"></p>',
    ];

    /**
     * @var string[]
     */
    private array $htmlResults = [
        '<?xml version="1.0"?>
<file>
  <unit id="/html/body/h1">
    <segment id="/html/body/h1/text()">
      <source>Report</source>
    </segment>
  </unit>
  <group id="/html/body/table">
    <group id="/html/body/table/tr[1]">
      <unit id="/html/body/table/tr[1]/td[1]">
        <segment id="/html/body/table/tr[1]/td[1]/text()">
          <source>Text in cell r1-c1</source>
        </segment>
      </unit>
      <unit id="/html/body/table/tr[1]/td[2]">
        <segment id="/html/body/table/tr[1]/td[2]/text()">
          <source>Text in cell r1-c2</source>
        </segment>
      </unit>
    </group>
    <group id="/html/body/table/tr[2]">
      <unit id="/html/body/table/tr[2]/td[1]">
        <segment id="/html/body/table/tr[2]/td[1]/text()">
          <source>Text in cell r2-c1</source>
        </segment>
      </unit>
      <unit id="/html/body/table/tr[2]/td[2]">
        <segment id="/html/body/table/tr[2]/td[2]/text()">
          <source>Text in cell r2-c2</source>
        </segment>
      </unit>
    </group>
  </group>
  <unit id="/html/body/p">
    <segment id="/html/body/p/text()">
      <source>All rights reserved (c) Gandalf Inc.</source>
    </segment>
  </unit>
</file>
',
        '<?xml version="1.0"?>
<file/>
',
        '<?xml version="1.0"?>
<file>
  <unit id="/html/body/p">
    <segment id="/html/body/p/text()">
      <source>Hello</source>
      <target>Bonjour</target>
    </segment>
  </unit>
</file>
',
        '<?xml version="1.0"?>
<file>
  <unit id="/html/body/p">
    <segment id="/html/body/p/text()">
      <source>Hello Mathieu, please visits</source>
      <target>Bonjour Mathieu</target>
    </segment>
    <segment id="/html/body/p/a/text()[1]">
      <source> </source>
      <target>Le site d&#xB4;elasticms</target>
    </segment>
    <segment id="/html/body/p/a/text()[2]">
      <source>The elastic</source>
    </segment>
    <segment id="/html/body/p/a/b/text()">
      <source>ms</source>
    </segment>
    <segment id="/html/body/p/a/text()[3]">
      <source> web site</source>
    </segment>
  </unit>
</file>
',
        '<?xml version="1.0"?>
<file>
  <unit id="/html/body/p">
    <segment id="/html/body/p/title">
      <source>Information about Mount Hood</source>
      <target>Information &#xE0; propos de Mount Hood</target>
    </segment>
    <segment id="/html/body/p/text()">
      <source>This is Mount Hood: </source>
      <target>Ceci est Mount Hood:</target>
    </segment>
    <segment id="/html/body/p/img/alt">
      <source>Mount Hood with its snow-covered top</source>
      <target>Mount Hood avec son sommet enneig&#xE9;</target>
    </segment>
  </unit>
</file>
',
    ];

    private XliffService $xliffService;

    protected function setUp(): void
    {
        $this->xliffService = new XliffService();
    }

    public function testXliffExport(): void
    {
        $loop = 0;
        foreach ($this->htmlSources as $htmlSource) {
            $node = new \SimpleXMLElement('<file/>');
            $this->xliffService->htmlNode($node, $htmlSource, $this->htmlTarget[$loop], 'en', 'fr');
            $dom = \dom_import_simplexml($node)->ownerDocument;
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
            $xml = $dom->saveXML();
            echo $xml;
            $this->assertEquals($this->htmlResults[$loop], $xml);
            ++$loop;
        }
    }
}

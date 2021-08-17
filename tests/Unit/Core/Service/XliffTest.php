<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Service;

use EMS\CoreBundle\Service\XliffService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class XliffTest extends KernelTestCase
{
    private XliffService $xliffService;

    protected function setUp(): void
    {
        $this->xliffService = new XliffService();
    }

    public function testXliffExtractions(): void
    {
        $finder = new Finder();
        $finder->in(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Resources', 'Xliff', 'Extractions']))->directories();

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $fileNameWithExtension = $file->getRelativePathname();
            $htmlSource = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'source.html');
            $htmlTarget = '';
            if (\file_exists($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html')) {
                $htmlTarget = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html');
            }
            $node = new \SimpleXMLElement('<file/>');
            $this->assertNotFalse($node);
            $this->xliffService->htmlNode($node, $htmlSource ?: '', $htmlTarget ?: '', 'en', 'fr');
            $expected = new \SimpleXMLElement($absoluteFilePath.DIRECTORY_SEPARATOR.'expected.xlif', 0, true);
            $this->assertEquals($expected, $node, \sprintf('testXliffExtractions: %s', $fileNameWithExtension));
        }
    }
}

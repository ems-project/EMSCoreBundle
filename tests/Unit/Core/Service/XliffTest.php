<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Unit\Core\Service;

use EMS\CoreBundle\Helper\Xliff\XliffExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class XliffTest extends KernelTestCase
{
    public function testXliffExtractions(): void
    {
        $finder = new Finder();
        $finder->in(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Resources', 'Xliff', 'Extractions']))->directories();

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $fileNameWithExtension = $file->getRelativePathname();
            $htmlSource = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'source.html');
            $this->assertNotFalse($htmlSource);
            $htmlTarget = null;
            if (\file_exists($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html')) {
                $htmlTarget = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html');
            }

            foreach (XliffExtractor::XLIFF_VERSIONS as $version) {
                $xliffParser = new XliffExtractor('en', 'fr', $version);
                $document = $xliffParser->addDocument('contentType', 'ouuid_1', 'revisionId_1');
                $xliffParser->addSimpleField($document, 'title_%locale%', 'Foo', 'Bar');
                $document = $xliffParser->addDocument('contentType', 'ouuid_2', 'revisionId_2');
                $xliffParser->addSimpleField($document, 'title_%locale%', 'Hello', 'Bonjour');
                $xliffParser->addSimpleField($document, 'keywords_%locale%', 'test xliff');
                $xliffParser->addHtmlField($document, '%locale%.body', $htmlSource, $htmlTarget ?: null);

                $expectedFilename = $absoluteFilePath.DIRECTORY_SEPARATOR.'expected_'.$version.'.xlif';
                if (!\file_exists($expectedFilename)) {
                    $xliffParser->saveXML($expectedFilename);
                }

                $expected = new \SimpleXMLElement($expectedFilename, 0, true);
                $actual = new \SimpleXMLElement($xliffParser->asXML()->saveXML());

                $this->assertEquals($expected, $actual, \sprintf('testXliffExtractions: %s', $fileNameWithExtension));
            }
        }
    }
}

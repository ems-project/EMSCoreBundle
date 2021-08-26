<?php

declare(strict_types=1);

namespace Unit\Core\Helper\Xliff;

use EMS\CoreBundle\Helper\Xliff\Importer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class ImporterTest extends KernelTestCase
{
    public function testXliffImports(): void
    {
        $finder = new Finder();
        $finder->in(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Imports']))->directories();

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $fileNameWithExtension = $file->getRelativePathname();
            $translatedXliff = new \SimpleXMLElement($absoluteFilePath.DIRECTORY_SEPARATOR.'translated.xlf', 0, true);

            $extractor = new Importer($translatedXliff);
            foreach ($extractor->getDocuments() as $document) {
                $corresponding = \file_get_contents(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Revisions', $document->getContentType(), $document->getOuuid(), $document->getRevisionId().'.json']));
                $this->assertNotFalse($corresponding);
                $correspondingJson = \json_decode($corresponding, true);
                $this->assertIsArray($correspondingJson);
                $document->importTranslations($correspondingJson);

                $expectedFilename = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Translated', $document->getContentType().'-'.$document->getOuuid().'-'.$document->getRevisionId().'.json']);
                if (!\file_exists($expectedFilename)) {
                    \file_put_contents($expectedFilename, \json_encode($correspondingJson, JSON_PRETTY_PRINT));
                }
                $expected = \json_decode(\file_get_contents($expectedFilename), true);
                $this->assertEquals($expected, $correspondingJson);
            }
        }
    }
}

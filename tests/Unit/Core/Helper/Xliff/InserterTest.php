<?php

declare(strict_types=1);

namespace Unit\Core\Helper\Xliff;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Helper\Xliff\Inserter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class InserterTest extends KernelTestCase
{
    public function testAttrWithCurlBracket(): void
    {
        $document = new \DOMDocument();
        $html = new \DOMElement('html');
        $document->appendChild($html);
        $body = new \DOMElement('body');
        $html->appendChild($body);
        $link = new \DOMElement('a', 'Click here');
        $body->appendChild($link);
        $link->setAttribute('href', '%{BASE_URL_CURRENT}%/instructions/persons/specific/childsitter.html');

        $this->assertEquals('<body><a href="%{BASE_URL_CURRENT}%/instructions/persons/specific/childsitter.html">Click here</a></body>', $document->saveXML($body));
    }

    public function testXliffImports(): void
    {
        $finder = new Finder();
        $finder->in(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Imports']))->directories();

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $fileNameWithExtension = $file->getRelativePathname();

            $importer = Inserter::fromFile($absoluteFilePath.DIRECTORY_SEPARATOR.'translated.xlf');
            foreach ($importer->getDocuments() as $document) {
                $corresponding = \file_get_contents(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Revisions', $document->getContentType(), $document->getOuuid(), $document->getRevisionId().'.json']));
                $this->assertNotFalse($corresponding);
                $correspondingJson = Json::decode($corresponding);
                $this->assertIsArray($correspondingJson);
                $target = [];
                $document->extractTranslations($correspondingJson, $target);

                $expectedFilename = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Translated', $document->getContentType().'-'.$document->getOuuid().'-'.$document->getRevisionId().'.json']);
                if (!\file_exists($expectedFilename)) {
                    \file_put_contents($expectedFilename, \json_encode($target, JSON_PRETTY_PRINT));
                }
                $expected = \json_decode(\file_get_contents($expectedFilename), true);
                $this->assertEquals($expected, $target, \sprintf('For the document ems://%s:%s revision %s during the test %s', $document->getContentType(), $document->getOuuid(), $document->getRevisionId(), $fileNameWithExtension));
            }
        }
    }
}

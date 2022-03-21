<?php

declare(strict_types=1);

namespace Unit\Core\Helper\Xliff;

use EMS\CoreBundle\Helper\Xliff\Extractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class ExtractorTest extends KernelTestCase
{
    public function testXliffExtractions(): void
    {
        $finder = new Finder();
        $finder->in(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Extractions']))->directories();

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            $fileNameWithExtension = $file->getRelativePathname();
            $htmlSource = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'source.html');
            $this->assertNotFalse($htmlSource);
            $htmlTarget = null;
            if (\file_exists($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html')) {
                $htmlTarget = \file_get_contents($absoluteFilePath.DIRECTORY_SEPARATOR.'target.html');
            }

            foreach (Extractor::XLIFF_VERSIONS as $version) {
                $xliffParser = new Extractor('en', 'fr', $version, \explode(',', 'a,abbr,acronym,applet,b,bdo,big,blink,br,button,cite,code,del,dfn,em,embed,face,font,i,iframe,img,input,ins,kbd,label,map,nobr,object,param,q,rb,rbc,rp,rt,rtc,ruby,s,samp,select,small,span,spacer,strike,strong,sub,sup,symbol,textarea,tt,u,var,wbr'));
                $document = $xliffParser->addDocument('contentType', 'ouuid_1', 'revisionId_1');
                $xliffParser->addSimpleField($document, '[title_%locale%]', 'Foo', 'Bar');
                $document = $xliffParser->addDocument('contentType', 'ouuid_2', 'revisionId_2');
                $xliffParser->addSimpleField($document, '[title_%locale%]', 'Hello', 'Bonjour');
                $xliffParser->addSimpleField($document, '[keywords_%locale%]', 'test xliff');
                $xliffParser->addHtmlField($document, '[%locale%][body]', $htmlSource, $htmlTarget ?: null);
//                $xliffParser->addSimpleField($document, '[%locale%][body2]', $htmlSource, $htmlTarget ?: null);

                $this->saveAndCompare($absoluteFilePath, $version, $xliffParser, $fileNameWithExtension, 'UTF-8');
                $this->saveAndCompare($absoluteFilePath, $version, $xliffParser, $fileNameWithExtension, 'us-ascii');
            }
        }
    }

    public function saveAndCompare(string $absoluteFilePath, string $version, Extractor $xliffParser, string $fileNameWithExtension, string $encoding): void
    {
        $expectedFilename = $absoluteFilePath.DIRECTORY_SEPARATOR.'expected_'.$encoding.$version.'.xlf';
        if (!\file_exists($expectedFilename)) {
            $xliffParser->saveXML($expectedFilename, $encoding);
        }
        $expected = new \SimpleXMLElement($expectedFilename, 0, true);
        $actual = new \SimpleXMLElement($xliffParser->asXML()->saveXML());

        $this->assertEquals($expected, $actual, \sprintf('testXliffExtractions: %s', $fileNameWithExtension));
    }
}

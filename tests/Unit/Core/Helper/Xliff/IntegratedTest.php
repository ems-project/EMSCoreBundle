<?php

declare(strict_types=1);

namespace Core\Helper\Xliff;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Helper\Html;
use EMS\CoreBundle\Helper\Xliff\Extractor;
use EMS\CoreBundle\Helper\Xliff\Inserter;
use EMS\CoreBundle\Helper\Xliff\InsertionRevision;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

class IntegratedTest extends KernelTestCase
{
    public function testExtractInsert(): void
    {
        $finder = new Finder();
        $resourcesPath = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'Integrated']);
        $finder->name('*.json')->in($resourcesPath.DIRECTORY_SEPARATOR.'sources');

        foreach ($finder as $file) {
            $basename = $file->getBasename('.json');
            list($ouuid, $revisionId) = \explode('_', $basename);
            $source = Json::decode(\file_get_contents($file->getPathname()));
            $target = Json::decode(\file_get_contents(\join(DIRECTORY_SEPARATOR, [$resourcesPath, 'targets', $file->getBasename()])));
            $xliff = $this->generateXliff($ouuid, $revisionId, $source, $target);

            $xliffFilename = $this->saveAndCompare($file->getPath(), $xliff, $basename);

            $inserter = Inserter::fromFile($xliffFilename);
            $this->assertEquals(1, $inserter->count(), 'Only one document is expected');
            foreach ($inserter->getDocuments() as $document) {
                $this->insertDocument($document, $source, $target);
            }
        }
    }

    private function generateXliff(string $ouuid, string $revisionId, array $source, array $target): Extractor
    {
        $xliffParser = new Extractor('nl', 'de', Extractor::XLIFF_1_2);
        $document = $xliffParser->addDocument('content_type', $ouuid, $revisionId);
        foreach (['title', 'title_short'] as $field) {
            $xliffParser->addSimpleField($document, "[$field]", $source[$field] ?? null, $target[$field] ?? null, true);
        }
        foreach (['introduction', 'description'] as $field) {
            $xliffParser->addHtmlField($document, "[$field]", $source[$field] ?? null, $target[$field] ?? null, true);
        }

        return $xliffParser;
    }

    public function saveAndCompare(string $absoluteFilePath, Extractor $xliffParser, string $baseName): string
    {
        $expectedFilename = \join(DIRECTORY_SEPARATOR, [$absoluteFilePath, '..', 'xliffs', $baseName.'.xlf']);
        if (!\file_exists($expectedFilename)) {
            $xliffParser->saveXML($expectedFilename);
        }

        $temp_file = \tempnam(\sys_get_temp_dir(), 'TC-');
        $xliffParser->saveXML($temp_file);

        $expected = \file_get_contents($expectedFilename);
        $actual = \file_get_contents($temp_file);

        $this->assertEquals($expected, $actual, \sprintf('testXliffExtractions: %s', $baseName));

        return $expectedFilename;
    }

    private function insertDocument(InsertionRevision $document, $source, $target)
    {
        $inserted = $source;
        $document->extractTranslations($source, $inserted);
        $inserted['locale'] = 'de';

        foreach ($source as $field => $value) {
            if (\in_array($field, ['introduction', 'description'])) {
                $this->assertEquals(Html::prettyPrint($inserted[$field]), Html::prettyPrint($target[$field] ?? null), \sprintf('Field %s for inserted document : %s', $field, $document->getOuuid()));
            } else {
                $this->assertEquals($target[$field] ?? null, $inserted[$field], \sprintf('Field %s for inserted document : %s', $field, $document->getOuuid()));
            }
        }
    }

    public function testImportOnliner(): void
    {
        $inserter = Inserter::fromFile(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'new_extract.xlf']));
        $this->assertEquals(30, $inserter->count(), 'Only one document is expected');
        foreach ($inserter->getDocuments() as $document) {
            $this->saveJson($document);
        }
    }

    private function saveJson(InsertionRevision $document)
    {
        $source = Json::decode(\file_get_contents(\join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'TestRevision', \sprintf('%s_%s.json', $document->getOuuid(), $document->getRevisionId())])));
        $inserted = $source;
        $document->extractTranslations($source, $inserted);
        unset($inserted['date_modification']);
        unset($inserted['_contenttype']);
        unset($inserted['_sha1']);
        unset($inserted['_published_datetime']);
        $inserted['locale'] = 'de';
        $filename = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'TestRevisionOut', \sprintf('%s_%s.json', $document->getOuuid(), $document->getRevisionId())]);
        if (!\file_exists($filename)) {
            \file_put_contents($filename, Json::encode($inserted, true));
        }

        $this->assertEquals(\file_get_contents($filename), Json::encode($inserted, true), \sprintf('with test file %s', $document->getOuuid()));
    }
}

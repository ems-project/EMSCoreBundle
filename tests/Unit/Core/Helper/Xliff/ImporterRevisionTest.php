<?php

namespace EMS\CoreBundle\Tests\Unit\Core\Helper\Xliff;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use EMS\CoreBundle\Helper\Xliff\ImporterRevision;

class ImporterRevisionTest extends KernelTestCase
{

    public function testAttributeGetter(): void
    {
        $sourceFile = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'ImporterRevision', 'testAttributes_1.2.xlf']);

        $translatedXliff = new \SimpleXMLElement($sourceFile, 0, true);
        foreach ($translatedXliff->file as $document) {
            $this->forDocument($document, '1.2', $translatedXliff->getNamespaces());
        }

        $sourceFile = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'ImporterRevision', 'testAttributes_2.0.xlf']);

        $translatedXliff = new \SimpleXMLElement($sourceFile, 0, true);
        foreach ($translatedXliff->file as $document) {
            $this->forDocument($document, '2.0', $translatedXliff->getNamespaces());
        }
    }

    private function forDocument(\SimpleXMLElement $document, string $version, array $nameSpaces): void
    {
        $object = new ImporterRevision($document, $version, $nameSpaces);
    }
}
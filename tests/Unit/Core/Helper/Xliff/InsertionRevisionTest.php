<?php

declare(strict_types=1);

namespace Unit\Core\Helper\Xliff;

use EMS\CoreBundle\Helper\Xliff\InsertionRevision;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InsertionRevisionTest extends KernelTestCase
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

    /**
     * @param string[] $nameSpaces
     */
    private function forDocument(\SimpleXMLElement $document, string $version, array $nameSpaces): void
    {
        $object = new InsertionRevision($document, $version, $nameSpaces, null, null);
        foreach ($object->getTranslatedFields() as $field) {
            $this->assertNull($object->getAttributeValue($field, 'toto'));
            $this->assertEquals('en', $object->getAttributeValue($field->source, 'xml:lang', 'en'));
            $this->assertEquals('fr', $object->getAttributeValue($field->target, 'xml:lang', 'fr'));
            $this->assertNotNull($object->getAttributeValue($field, 'id'));
        }
    }
}

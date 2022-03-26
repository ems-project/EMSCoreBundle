<?php

declare(strict_types=1);

namespace Unit\Core\Helper\Xliff;

use EMS\CoreBundle\Helper\Xliff\InsertionRevision;
use EMS\CoreBundle\Helper\XML\DomHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InsertionRevisionTest extends KernelTestCase
{
    public function testAttributeGetter(): void
    {
        $sourceFile = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'ImporterRevision', 'testAttributes_1.2.xlf']);

        $document = new \DOMDocument();
        $document->loadXML(\file_get_contents($sourceFile));
        foreach ($document->getElementsByTagName('file') as $document) {
            $this->forDocument($document, '1.2');
        }

        $sourceFile = \join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'Resources', 'Xliff', 'ImporterRevision', 'testAttributes_2.0.xlf']);

        $document = new \DOMDocument();
        $document->loadXML(\file_get_contents($sourceFile));
        foreach ($document->getElementsByTagName('file') as $document) {
            $this->forDocument($document, '2.0');
        }
    }

    private function forDocument(\DOMElement $document, string $version): void
    {
        $nameSpaces = [];
        foreach (['xml'] as $ns) {
            $nameSpaces[$ns] = $document->ownerDocument->lookupNamespaceURI($ns);
        }

        $object = new InsertionRevision($document, $version, $nameSpaces, null, null);
        foreach ($object->getTranslatedFields() as $field) {
            $this->assertNull($object->getAttributeValue($field, 'toto'));
            if (\in_array($field->nodeName, ['trans-unit', 'segment'])) {
                $this->assertEquals('en', $object->getAttributeValue(DomHelper::getSingleElement($field, 'source'), 'xml:lang', 'en'));
                $this->assertNotNull($object->getAttributeValue($field, 'id'));
            }
        }
    }
}

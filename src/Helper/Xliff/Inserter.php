<?php

namespace EMS\CoreBundle\Helper\Xliff;

use EMS\CoreBundle\Helper\XML\DomHelper;

class Inserter
{
    private string $version;
    /** @var string[] */
    private array $nameSpaces = [];
    private \DOMNode $xliff;
    private ?string $sourceLocale;
    private ?string $targetLocale;

    private function __construct(\DOMDocument $document)
    {
        $this->xliff = DomHelper::getSingleNodeFromDocument($document, 'xliff');
        $this->version = DomHelper::getStringAttr($this->xliff, 'version');

        foreach (['xml'] as $ns) {
            $this->nameSpaces[$ns] = $document->lookupNamespaceURI($ns);
        }

        if (!\in_array($this->version, Extractor::XLIFF_VERSIONS)) {
            throw new \RuntimeException(\sprintf('Not supported %s XLIFF version', $this->version));
        }

        $this->sourceLocale = DomHelper::getNullStringAttr($this->xliff, 'srcLang');
        $this->targetLocale = DomHelper::getNullStringAttr($this->xliff, 'trgLang');
    }

    public static function fromFile(string $filename): Inserter
    {
        $xliff = new \DOMDocument('1.0', 'UTF-8');
        $xliff->load($filename);

        return new self($xliff);
    }

    /**
     * @return iterable<InsertionRevision>
     */
    public function getDocuments(): iterable
    {
        foreach ($this->xliff->childNodes as $document) {
            if (!$document instanceof \DOMElement) {
                continue;
            }
            yield new InsertionRevision($document, $this->version, $this->nameSpaces, $this->sourceLocale, $this->targetLocale);
        }
    }

    public function count(): int
    {
        $counter = 0;
        foreach ($this->xliff->childNodes as $document) {
            if (!$document instanceof \DOMElement) {
                continue;
            }
            ++$counter;
        }

        return $counter;
    }
}

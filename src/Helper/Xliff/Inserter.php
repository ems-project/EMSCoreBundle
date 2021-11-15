<?php

namespace EMS\CoreBundle\Helper\Xliff;

class Inserter
{
    private string $version;
    /** @var string[] */
    private array $nameSpaces;
    private \SimpleXMLElement $xliff;
    private ?string $sourceLocale;
    private ?string $targetLocale;

    private function __construct(\SimpleXMLElement $xliff)
    {
        $this->version = \strval($xliff['version']);
        if (!\in_array($this->version, Extractor::XLIFF_VERSIONS)) {
            throw new \RuntimeException(\sprintf('Not supported %s XLIFF version', $this->version));
        }

        $srcLang = $xliff['srcLang'];
        $this->sourceLocale = (null === $srcLang ? null : \strval($srcLang));
        $trgLang = $xliff['trgLang'];
        $this->targetLocale = (null === $trgLang ? null : \strval($trgLang));
        $this->nameSpaces = $xliff->getNameSpaces(true);
        $xliff->registerXPathNamespace('ns', $this->nameSpaces['']);
        $this->xliff = $xliff;
    }

    public static function fromFile(string $filename): Inserter
    {
        $xliff = new \SimpleXMLElement($filename, 0, true);

        return new self($xliff);
    }

    /**
     * @return iterable<InsertionRevision>
     */
    public function getDocuments(): iterable
    {
        foreach ($this->xliff->children() as $document) {
            $document->registerXPathNamespace('ns', $this->nameSpaces['']);
            yield new InsertionRevision($document, $this->version, $this->nameSpaces, $this->sourceLocale, $this->targetLocale);
        }
    }

    public function count(): int
    {
        return $this->xliff->children()->count();
    }
}

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
        $this->xliff = $xliff;
        $this->nameSpaces = $xliff->getNameSpaces(true);
    }

    public static function fromFile(string $filename): Inserter
    {
        $xliff = new \SimpleXMLElement($filename, 0, true);
        self::registerNamespaces($xliff);

        return new self($xliff);
    }

    /**
     * @return iterable<InsertionRevision>
     */
    public function getDocuments(): iterable
    {
        foreach ($this->xliff->children() as $document) {
            self::registerNamespaces($document);
            yield new InsertionRevision($document, $this->version, $this->nameSpaces, $this->sourceLocale, $this->targetLocale);
        }
    }

    public function count(): int
    {
        return $this->xliff->children()->count();
    }

    private static function registerNamespaces(\SimpleXMLElement $document): void
    {
        foreach ($document->getDocNamespaces() as $strPrefix => $strNamespace) {
            if (0 == \strlen($strPrefix)) {
                $strPrefix = 'xliff';
            }
            $document->registerXPathNamespace($strPrefix, $strNamespace);
        }
    }
}

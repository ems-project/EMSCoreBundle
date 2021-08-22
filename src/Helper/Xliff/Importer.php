<?php

namespace EMS\CoreBundle\Helper\Xliff;

class Importer
{
    private string $version;
    private \SimpleXMLElement $xliff;

    public function __construct(\SimpleXMLElement $xliff)
    {
        $this->version = \strval($xliff['version']);
        if (!\in_array($this->version, Extractor::XLIFF_VERSIONS)) {
            throw new \RuntimeException(\sprintf('Not supported %s XLIFF version', $this->version));
        }

        $this->xliff = $xliff;
    }

    /**
     * @return iterable<ImporterRevision>
     */
    public function getDocuments(): iterable
    {
        foreach ($this->xliff->children() as $document) {
            yield new ImporterRevision($document, $this->version);
        }
    }
}

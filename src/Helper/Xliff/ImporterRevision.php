<?php

namespace EMS\CoreBundle\Helper\Xliff;

class ImporterRevision
{
    private string $version;
    private string $contentType;
    private string $ouuid;
    private string $revisionId;

    public function __construct(\SimpleXMLElement $document, string $version)
    {
        $this->version = $version;
        $original = \strval($document['original']);
        list($this->contentType, $this->ouuid, $this->revisionId) = \explode(':', $original);
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getOuuid(): string
    {
        return $this->ouuid;
    }

    public function getRevisionId(): string
    {
        return $this->revisionId;
    }
}

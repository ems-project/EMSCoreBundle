<?php
namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;

class ExportDocuments
{
    /** @var string */
    private $action;
    /** @var string */
    private $query;
    /** @var string */
    private $format;
    /** @var bool */
    private $withBusinessKey = false;
    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $environment;


    public function __construct(ContentType $contentType, string $action = '', string $query = '{}')
    {
        $this->action = $action;
        $this->contentType = $contentType;
        $this->query = $query;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): ExportDocuments
    {
        $this->action = $action;
        return $this;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): ExportDocuments
    {
        $this->query = $query;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): ExportDocuments
    {
        $this->format = $format;
        return $this;
    }

    public function isWithBusinessKey(): bool
    {
        return $this->withBusinessKey;
    }

    public function setWithBusinessKey(bool $withBusinessKey): ExportDocuments
    {
        $this->withBusinessKey = $withBusinessKey;
        return $this;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function setContentType(ContentType $contentType): ExportDocuments
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): ExportDocuments
    {
        $this->environment = $environment;
        return $this;
    }
}

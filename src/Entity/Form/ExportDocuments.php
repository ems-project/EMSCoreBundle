<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use EMS\CoreBundle\Entity\ContentType;

class ExportDocuments
{
    private ?string $format = null;
    private bool $withBusinessKey = false;
    private ?string $environment = null;

    public function __construct(private ContentType $contentType, private string $action = '', private string $query = '{}')
    {
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

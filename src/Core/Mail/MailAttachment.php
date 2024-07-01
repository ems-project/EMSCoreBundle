<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mail;

class MailAttachment
{
    public function __construct(private readonly string $path, private readonly ?string $name = null, private readonly ?string $contentType = null)
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}

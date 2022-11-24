<?php

namespace EMS\CoreBundle\Entity\Context;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;

class DocumentImportContext
{
    private Environment $environment;
    private ContentType $contentType;
    private string $lockUser;
    private bool $shouldRawImport;
    private bool $shouldOnlyChanged = false;
    private bool $shouldIndexInDefaultEnv;
    private bool $shouldFinalize;
    private bool $shouldForce;

    public function __construct(
        ContentType $contentType,
        string $lockUser,
        bool $shouldRawImport,
        bool $shouldIndexInDefaultEnv,
        bool $shouldFinalize,
        bool $shouldForceImport
    ) {
        $this->contentType = $contentType;
        $this->shouldIndexInDefaultEnv = $shouldIndexInDefaultEnv;
        $this->lockUser = $lockUser;
        $this->shouldRawImport = $shouldRawImport;
        $this->shouldFinalize = $shouldFinalize;
        $this->shouldForce = $shouldForceImport;
        $this->environment = $this->contentType->giveEnvironment();
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function getLockUser(): string
    {
        return $this->lockUser;
    }

    public function shouldRawImport(): bool
    {
        return $this->shouldRawImport;
    }

    public function shouldOnlyChanged(): bool
    {
        return $this->shouldOnlyChanged;
    }

    public function setShouldOnlyChanged(bool $onlyChanged): self
    {
        $this->shouldOnlyChanged = $onlyChanged;

        return $this;
    }

    public function shouldIndexInDefaultEnv(): bool
    {
        return $this->shouldIndexInDefaultEnv;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function shouldFinalize(): bool
    {
        return $this->shouldFinalize;
    }

    public function shouldForce(): bool
    {
        return $this->shouldForce;
    }
}

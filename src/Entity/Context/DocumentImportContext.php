<?php

namespace EMS\CoreBundle\Entity\Context;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;

class DocumentImportContext
{
    private readonly Environment $environment;
    private bool $shouldOnlyChanged = false;

    public function __construct(
        private readonly ContentType $contentType,
        private readonly string $lockUser,
        private readonly bool $shouldRawImport,
        private readonly bool $shouldIndexInDefaultEnv,
        private readonly bool $shouldFinalize,
        private readonly bool $shouldForce,
    ) {
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

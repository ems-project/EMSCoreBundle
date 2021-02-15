<?php

namespace EMS\CoreBundle\Entity\Context;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;

class DocumentImportContext
{
    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $lockUser;
    /** @var bool */
    private $shouldRawImport;
    /** @var bool */
    private $shouldIndexInDefaultEnv;
    /** @var Environment */
    private $environment;
    /** @var bool */
    private $shouldFinalize;
    /** @var bool */
    private $shouldForce;

    public function __construct(ContentType $contentType, string $lockUser, bool $shouldRawImport, bool $shouldIndexInDefaultEnv, bool $shouldFinalize, bool $shouldForceImport)
    {
        $this->contentType = $contentType;
        $this->shouldIndexInDefaultEnv = $shouldIndexInDefaultEnv;
        $this->lockUser = $lockUser;
        $this->shouldRawImport = $shouldRawImport;
        $this->shouldFinalize = $shouldFinalize;
        $this->shouldForce = $shouldForceImport;
        /** @var Environment $env */
        $env = $this->contentType->getEnvironment();
        $this->environment = $env;
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

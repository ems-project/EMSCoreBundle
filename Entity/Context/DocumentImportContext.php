<?php

declare(strict_types=1);

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
    /** @var bool */
    private $shouldSignData;
    /** @var Environment */
    private $environment;
    /** @var bool */
    private $shouldFinalize;
    /** @var bool */
    private $shouldForce;

    public function __construct(ContentType $contentType, string $lockUser, bool $shouldRawImport, bool $signData, bool $shouldIndexInDefaultEnv, bool $shouldFinalize, bool $shouldForceImport)
    {
        $this->contentType = $contentType;
        $this->shouldIndexInDefaultEnv = $shouldIndexInDefaultEnv;
        $this->shouldSignData = $signData;
        $this->lockUser = $lockUser;
        $this->shouldRawImport = $shouldRawImport;
        $this->shouldFinalize = $shouldFinalize;
        $this->shouldForce = $shouldForceImport;
        $this->environment = $this->contentType->getEnvironment();
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

    public function shouldSignData(): bool
    {
        return $this->shouldSignData;
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

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType;

use EMS\CoreBundle\Entity\EntityInterface;
use EMS\CoreBundle\Entity\Environment;
use EMS\Helpers\Standard\Hash;

class ContentTypeUnreferenced implements EntityInterface
{
    public readonly int $environmentId;
    public readonly ?string $environmentColor;
    public readonly string $environmentLabel;

    public function __construct(
        public readonly string $name,
        public readonly Environment $environment,
        public readonly int $count,
    ) {
        $this->environmentId = $this->environment->getId();
        $this->environmentColor = $this->environment->getColor();
        $this->environmentLabel = $this->environment->getLabel();
    }

    #[\Override]
    public function getId(): string
    {
        return Hash::string($this->environment->getName().$this->name);
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }
}

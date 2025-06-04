<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Version;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\Helpers\Standard\DateTime;

class Versioning
{
    private ?FieldType $rootFieldType = null;

    /**
     * @param string[] $tags
     */
    public function __construct(
        private VersionFields $fields,
        private VersionOptions $options,
        private array $tags
    ) {
    }

    public function enabled(): bool
    {
        return null !== $this->field(VersionFields::DATE_FROM) && null !== $this->field(VersionFields::DATE_TO);
    }

    public function field(string $name): ?string
    {
        return $this->getFields()[$name] ?? null;
    }

    public function option(string $name): bool
    {
        return $this->getOptions()[$name] ?? false;
    }

    public function getFields(): VersionFields
    {
        return $this->fields;
    }

    public function getOptions(): VersionOptions
    {
        return $this->options;
    }

    /** @return string[] */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function setFields(VersionFields $versionFields): void
    {
        $this->fields = $versionFields;
    }

    public function setOptions(VersionOptions $versionOptions): void
    {
        $this->options = $versionOptions;
    }

    public function setRootFieldType(?FieldType $rootFieldType): void
    {
        $this->rootFieldType = $rootFieldType;
    }

    /** @param string[] $tags */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function dateFormat(): string
    {
        if (null === $fieldFrom = $this->field(VersionFields::DATE_FROM)) {
            throw new \RuntimeException('Version from not defined');
        }

        if (null === $fromFieldType = $this->rootFieldType?->findChildByName($fieldFrom)) {
            throw new \RuntimeException(\sprintf('Version date from "%s" field not found.', $fieldFrom));
        }

        if (DateFieldType::class === $fromFieldType->getType()) {
            $mappingFormat = $fromFieldType->getMappingOption('format');

            return $mappingFormat ? DateTime::convertFormat('java', $mappingFormat) : \DateTimeInterface::ATOM;
        }

        return \DateTimeInterface::ATOM;
    }
}

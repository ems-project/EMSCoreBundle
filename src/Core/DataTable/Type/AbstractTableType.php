<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\DataTableFormat;
use EMS\CoreBundle\Roles;
use EMS\Helpers\Standard\Hash;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractTableType implements DataTableTypeInterface
{
    protected DataTableFormat $format = DataTableFormat::TABLE;

    public const LOAD_MAX_ROWS = 400;

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
    }

    /**
     * @param array<mixed> $options
     */
    #[\Override]
    public function getContext(array $options): mixed
    {
        return null;
    }

    #[\Override]
    public function getExportFormats(): array
    {
        return [];
    }

    #[\Override]
    public function getHash(): string
    {
        return Hash::string(static::class);
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_USER];
    }

    #[\Override]
    public function setFormat(DataTableFormat $format): void
    {
        $this->format = $format;
    }

    public function getLoadMaxRows(): int
    {
        return self::LOAD_MAX_ROWS;
    }
}

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

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
    }

    /**
     * @param array<mixed> $options
     */
    public function getContext(array $options): mixed
    {
        return null;
    }

    public function getExportFormats(): array
    {
        return [];
    }

    public function getHash(): string
    {
        return Hash::string(\get_class($this));
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return [Roles::ROLE_USER];
    }

    public function setFormat(DataTableFormat $format): void
    {
        $this->format = $format;
    }
}

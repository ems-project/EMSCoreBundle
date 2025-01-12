<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\DataTableFormat;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface DataTableTypeInterface
{
    /**
     * @param array<mixed> $options
     */
    public function getContext(array $options): mixed;

    /**
     * @return DataTableFormat[]
     */
    public function getExportFormats(): array;

    /**
     * @return string[]
     */
    public function getRoles(): array;

    public function getHash(): string;

    public function configureOptions(OptionsResolver $optionsResolver): void;

    public function setFormat(DataTableFormat $format): void;
}

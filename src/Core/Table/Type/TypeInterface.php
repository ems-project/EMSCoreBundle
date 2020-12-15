<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Table\Type;

use EMS\CoreBundle\Core\Table\TableInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

interface TypeInterface
{
    public function buildTable(TableInterface $table, array $options): void;

    public function buildRows(): array;

    public function getName(): string;
    public function configureOptions(OptionsResolver $optionsResolver): void;
}
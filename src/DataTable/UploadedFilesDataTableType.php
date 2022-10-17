<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable;

use EMS\TableBundle\DataTable\Type\AbstractOrmDataTableType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UploadedFilesDataTableType extends AbstractOrmDataTableType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['test']);
    }
}
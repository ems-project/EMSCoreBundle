<?php

namespace EMS\CoreBundle\Core\Table;

use EMS\CoreBundle\Core\Table\Type\TypeRegistry;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DataTableFactory implements TableFactoryInterface
{
    /** @var TypeRegistry */
    private $typeRegistry;

    public function __construct(TypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public function create(string $typeClass, array $options = []): TableInterface
    {
        $type = $this->typeRegistry->getByTypeClass($typeClass);

        $optionsResolver = $this->getOptionResolver();
        $type->configureOptions($optionsResolver);
        $resolvedOptions = $optionsResolver->resolve($options);

        $dataTable = new DataTable($type->getName(), $resolvedOptions);
        $type->buildTable($dataTable, $resolvedOptions);

        $dataTable->setRows($type->buildRows());

        return $dataTable;
    }

    public function createByName(string $name): TableInterface
    {


    }


    protected function getOptionResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setDefaults([
                'template' => '@EMSCore/table/layout.html.twig',
                'processing' => false,
                'serverSide' => false,
            ]);

        return $optionsResolver;
    }
}
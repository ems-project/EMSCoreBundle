<?php

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DataFieldTypeCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('ems.form.field.datafieldtypepickertype')) {
            return;
        }

        /** @var Definition $definition */
        $definition = $container->findDefinition(
            'ems.form.field.datafieldtypepickertype'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'ems.form.datafieldtype'
        );

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall(
                    'addDataFieldType',
                    [new Reference($id)]
                );
            }
        }
    }
}

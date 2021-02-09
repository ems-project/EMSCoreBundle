<?php

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ViewTypeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('ems.form.field.viewtypepickertype')) {
            return;
        }

        /** @var Definition $definition */
        $definition = $container->findDefinition(
            'ems.form.field.viewtypepickertype'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'ems.form.viewtype'
        );

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall(
                    'addViewType',
                    [new Reference($id), $id]
                );
            }
        }
    }
}

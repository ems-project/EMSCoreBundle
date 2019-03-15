<?php

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use EMS\CoreBundle\Form\Form\ViewType;
use Symfony\Component\DependencyInjection\Definition;

class ViewTypeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
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
                    array(new Reference($id), $id)
                );
            }
        }
    }
}

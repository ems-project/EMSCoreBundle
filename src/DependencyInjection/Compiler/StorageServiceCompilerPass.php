<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class StorageServiceCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('ems.service.file')) {
            return;
        }

        /** @var Definition $definition */
        $definition = $container->findDefinition(
            'ems.service.file'
        );

        $taggedServices = $container->findTaggedServiceIds(
            'ems.storage'
        );

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall(
                    'addStorageService',
                    [new Reference($id)]
                );
            }
        }
    }
}

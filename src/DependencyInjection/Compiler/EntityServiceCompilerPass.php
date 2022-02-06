<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class EntityServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->registerEntityServices($container);
    }

    private function registerEntityServices(ContainerBuilder $container): void
    {
        if (!$container->has('emsco.helper.entities')) {
            return;
        }

        $definition = $container->findDefinition('emsco.helper.entities');
        $tags = $container->findTaggedServiceIds('emsco.entity.service');
        $transformerIds = \array_keys($tags);

        foreach ($transformerIds as $id) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}

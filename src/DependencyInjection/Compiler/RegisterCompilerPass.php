<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterCompilerPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        $this->registerContentTransformers($container);
    }

    private function registerContentTransformers(ContainerBuilder $container): void
    {
        if (!$container->has('ems_core.core_content_type_transformer.content_transformers')) {
            return;
        }

        $definition = $container->findDefinition('ems_core.core_content_type_transformer.content_transformers');
        $tags = $container->findTaggedServiceIds('ems_core.content_type.transformer');
        $transformerIds = \array_keys($tags);

        foreach ($transformerIds as $id) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}

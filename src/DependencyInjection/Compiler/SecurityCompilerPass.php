<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DependencyInjection\Compiler;

use EMS\CommonBundle\Common\Standard\Type;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class SecurityCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $firewallName = Type::string($container->getParameter('ems_core.security.firewall.core'));

        $loginManager = $container->getDefinition('ems_core.security.login_manager');

        $this->injectUserChecker($container, $loginManager, $firewallName);
        $this->injectRememberMeService($container, $loginManager, $firewallName);
    }

    private function injectUserChecker(ContainerBuilder $container, Definition $loginManager, string $firewallName): void
    {
        if ($container->has('security.user_checker.'.$firewallName)) {
            $loginManager->replaceArgument(2, new Reference('security.user_checker.'.$firewallName));
        }
    }

    private function injectRememberMeService(ContainerBuilder $container, Definition $loginManager, string $firewallName): void
    {
        if ($container->hasDefinition('security.authentication.rememberme.services.persistent.'.$firewallName)) {
            $loginManager->replaceArgument(5, new Reference('security.authentication.rememberme.services.persistent.'.$firewallName));
        } elseif ($container->hasDefinition('security.authentication.rememberme.services.simplehash.'.$firewallName)) {
            $loginManager->replaceArgument(5, new Reference('security.authentication.rememberme.services.simplehash.'.$firewallName));
        }
    }
}

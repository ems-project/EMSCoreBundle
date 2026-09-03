<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

use EMS\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\RegisterCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class EMSCoreBundle extends AbstractBundle
{
    final public const string TRANS_DOMAIN = 'EMSCoreBundle';
    final public const string TRANS_CORE = 'emsco-core';

    final public const string FONTAWESOME_VERSION = '4';

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new RegisterCompilerPass());
    }

    #[\Override]
    public function getContainerExtension(): ExtensionInterface
    {
        return new EMSCoreExtension();
    }
}

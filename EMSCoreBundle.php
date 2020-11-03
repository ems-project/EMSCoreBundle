<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

use EMS\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\ViewTypeCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EMSCoreBundle extends Bundle
{
    const TRANS_DOMAIN = 'EMSCoreBundle';

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ViewTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
    }
}

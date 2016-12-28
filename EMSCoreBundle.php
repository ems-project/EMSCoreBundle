<?php

namespace EMS\CoreBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use EMS\CoreBundle\DependencyInjection\Compiler\ViewTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

class EMSCoreBundle extends Bundle
{
	
//     public function getParent()
//     {
//         return 'FOSUserBundle';
//     }
    
	public function build(ContainerBuilder $container)
	{
		$container->addCompilerPass(new ViewTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
		$container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
		$container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
	}
}

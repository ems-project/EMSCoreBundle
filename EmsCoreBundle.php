<?php

namespace Ems\CoreBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Ems\CoreBundle\DependencyInjection\Compiler\ViewTypeCompilerPass;
use Ems\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use Ems\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

class EmsCoreBundle extends Bundle
{
	public function build(ContainerBuilder $container)
	{
		$container->addCompilerPass(new ViewTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
		$container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
		$container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
	}
}

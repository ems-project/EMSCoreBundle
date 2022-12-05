<?php

namespace EMS\CoreBundle;

use EMS\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\RegisterCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EMSCoreBundle extends Bundle
{
    final public const TRANS_DOMAIN = 'EMSCoreBundle';
    final public const TRANS_FORM_DOMAIN = 'emsco-forms';
    final public const TRANS_TWIG_DOMAIN = 'emsco-twigs';
    final public const TRANS_DOMAIN_VALIDATORS = 'emsco_validators';
    final public const TRANS_USER_DOMAIN = 'emsco-user';
    final public const TRANS_ENVIRONMENT_DOMAIN = 'emsco-environment';
    final public const FONTAWESOME_VERSION = '4';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new RegisterCompilerPass());
    }
}

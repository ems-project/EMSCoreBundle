<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

use EMS\CoreBundle\DependencyInjection\Compiler\DataFieldTypeCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\RegisterCompilerPass;
use EMS\CoreBundle\DependencyInjection\Compiler\StorageServiceCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EMSCoreBundle extends Bundle
{
    final public const string TRANS_DOMAIN = 'EMSCoreBundle';
    final public const string TRANS_COMPONENT = 'emsco-component';
    final public const string TRANS_CONTENTTYPE = 'emsco-contenttype';
    final public const string TRANS_CORE = 'emsco-core';
    final public const string TRANS_FORM_DOMAIN = 'emsco-forms';
    final public const string TRANS_TWIG_DOMAIN = 'emsco-twigs';
    final public const string TRANS_DOMAIN_VALIDATORS = 'emsco_validators';
    final public const string TRANS_USER_DOMAIN = 'emsco-user';
    final public const string TRANS_ENVIRONMENT_DOMAIN = 'emsco-environment';
    final public const string TRANS_MIMETYPES = 'emsco-mimetypes';

    final public const string FONTAWESOME_VERSION = '4';

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new DataFieldTypeCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new StorageServiceCompilerPass(), PassConfig::TYPE_OPTIMIZE);
        $container->addCompilerPass(new RegisterCompilerPass());
    }
}

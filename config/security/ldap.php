<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Security\Ldap\LdapAuthTokenLoginAuthenticator;
use EMS\CoreBundle\Security\Ldap\LdapConfig;
use EMS\CoreBundle\Security\Ldap\LdapFormLoginAuthenticator;
use EMS\CoreBundle\Security\Ldap\LdapUserProvider;
use Symfony\Component\Ldap\Ldap;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('ems_core.security.ldap.config', LdapConfig::class)
        ->args(['%ems_core.security.ldap.config%']);

    $services->set('emsco.security.provider.user_ldap', LdapUserProvider::class)
        ->args([
            service(Ldap::class),
            service('ems_core.security.ldap.config'),
            service('ems.repository.user'),
        ]);

    $services->set('emsco.security.authenticator.form_login_ldap', LdapFormLoginAuthenticator::class)
        ->args([
            service('ems_core.security.ldap.config'),
            service('emsco.security.provider.user_ldap'),
            service('router'),
        ]);

    $services->set('emsco.security.authenticator.auth_token_ldap', LdapAuthTokenLoginAuthenticator::class)
        ->args([
            service('ems_core.security.ldap.config'),
            service('emsco.security.provider.user_ldap'),
            service('ems.repository.auth_token'),
        ]);
};

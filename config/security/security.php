<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Repository\AuthTokenRepository;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Security\Authenticator\Authenticator;
use EMS\CoreBundle\Security\Authenticator\AuthTokenAuthenticator;
use EMS\CoreBundle\Security\Authenticator\AuthTokenLoginAuthenticator;
use EMS\CoreBundle\Security\Authenticator\FormLoginAuthenticator;
use EMS\CoreBundle\Security\Provider\UserApiProvider;
use EMS\CoreBundle\Security\Provider\UserProvider;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->private();

    $services->set('emsco.security.provider.user', UserProvider::class)
        ->args([
            service(UserRepository::class),
        ]);

    $services->set('emsco.security.provider.user_api', UserApiProvider::class)
        ->args([service(AuthTokenRepository::class)]);

    $services->set('emsco.security.authenticator.auth_token', AuthTokenAuthenticator::class);

    $services->set('emsco.security.authenticator.auth_token_login', AuthTokenLoginAuthenticator::class)
        ->args([
            service(AuthTokenRepository::class),
            '%ems_core.security.ldap.enabled%',
        ]);

    $services->set('emsco.security.authenticator.form_login', FormLoginAuthenticator::class)
        ->args([
            service('router'),
            '%ems_core.security.ldap.enabled%',
        ]);

    $services->set('ems_core.security.authenticator', Authenticator::class)
        ->args([
            service('emsco.security.authenticator.form_login'),
            service('security.user_authenticator'),
            service('request_stack'),
        ]);
};

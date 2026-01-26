<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Contracts\Revision\RevisionServiceInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->alias(RevisionServiceInterface::class, 'ems.service.revision');
};

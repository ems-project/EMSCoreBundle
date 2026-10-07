<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use EMS\CoreBundle\Repository\UploadedAssetRepository;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('EMS\\CoreBundle\\Repository\\', '../src/Repository/');

    $services->set(UploadedAssetRepository::class)->lazy(); // used in cache warmup
};

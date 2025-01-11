<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Tests\Integration\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EMS\ClientHelperBundle\EMSClientHelperBundle;
use EMS\CommonBundle\EMSCommonBundle;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    #[\Override]
    public function getCacheDir(): string
    {
        return \sys_get_temp_dir().'/cache-'.\spl_object_hash($this);
    }

    #[\Override]
    public function getLogDir(): string
    {
        return \sys_get_temp_dir().'/log-'.\spl_object_hash($this);
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[\Override]
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new MonologBundle(),
            new SecurityBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
            new EMSCommonBundle(),
            new EMSClientHelperBundle(),
            new EMSCoreBundle(),
        ];
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/config/config.yaml');
        $loader->load(__DIR__.'/config/security.yaml');
    }
}

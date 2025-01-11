<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Config;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class ConfigValueResolver implements ValueResolverInterface
{
    /**
     * @param ServiceLocator<ConfigFactoryInterface> $configFactories
     */
    public function __construct(
        private ServiceLocator $configFactories,
    ) {
    }

    /**
     * @return iterable<ConfigInterface>
     */
    #[\Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $argumentType = $argument->getType();
        if (!$argumentType || !\is_subclass_of($argumentType, ConfigInterface::class)) {
            return [];
        }

        $hash = $request->attributes->getAlnum('hash');

        return [$this->configFactories->get($argumentType)->createFromHash($hash)];
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Config;

use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractConfigFactory implements ConfigFactoryInterface
{
    private ?StorageManager $storageManager = null;
    private ?RequestStack $requestStack = null;

    public function setRequestStack(?RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param array<mixed> $options
     */
    abstract protected function create(string $hash, array $options): ConfigInterface;

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    abstract protected function resolveOptions(array $options): array;

    #[\Override]
    public function createFromHash(string $hash): ConfigInterface
    {
        $options = $this->getOptions($hash);

        return $this->createFromOptions($options);
    }

    #[\Override]
    public function createFromOptions(array $options): ConfigInterface
    {
        $resolvedOptions = $this->resolveOptions($options);
        $hash = $this->getHash($options);

        return $this->create($hash, $resolvedOptions);
    }

    #[\Override]
    public function createFromRequest(): ConfigInterface
    {
        if (null === $this->requestStack) {
            throw new \RuntimeException('Request stack not injected');
        }

        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new \RuntimeException('No current request');
        }

        $hash = $request->attributes->getAlnum('hash');
        if (!\is_string($hash)) {
            throw new \RuntimeException('Could not find request hash attribute on request');
        }

        return $this->createFromHash($hash);
    }

    public function getStorageManager(): StorageManager
    {
        return $this->storageManager ?: throw new \Exception('Storage manager not set');
    }

    public function setStorageManager(StorageManager $storageManager): void
    {
        $this->storageManager = $storageManager;
    }

    /**
     * @param array<mixed> $options
     */
    protected function getHash(array $options): string
    {
        return $this->getStorageManager()->saveConfig($options);
    }

    /**
     * @return array<mixed>
     */
    protected function getOptions(string $hash): array
    {
        try {
            return Json::decode($this->getStorageManager()->getContents($hash));
        } catch (NotFoundException) {
            throw new NotFoundHttpException();
        }
    }
}

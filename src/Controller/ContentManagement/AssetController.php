<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController extends AbstractController
{
    /**
     * @param array<string, mixed> $assetConfig
     */
    public function __construct(
        private readonly Processor $processor,
        protected array $assetConfig
    ) {
    }

    public function asset(string $hash, string $hash_config, string $filename, Request $request): Response
    {
        $this->closeSession($request);
        try {
            return $this->processor->getResponse($request, $hash, $hash_config, $filename);
        } catch (NotFoundException) {
            throw new NotFoundHttpException(\sprintf('File %s/%s/%s not found', $hash_config, $hash, $filename));
        }
    }

    public function assetProcessor(Request $request, string $processor, string $hash): Response
    {
        $this->closeSession($request);
        $assetConfig = $this->assetConfig[$processor] ?? [];
        if (!\is_array($assetConfig)) {
            throw new \RuntimeException('Unexpected asset config type');
        }

        if (!isset($assetConfig[EmsFields::ASSET_CONFIG_TYPE])) {
            $assetConfig[EmsFields::ASSET_CONFIG_TYPE] = EmsFields::ASSET_CONFIG_TYPE_IMAGE;
        }
        $filename = $processor;
        $quality = (int) ($assetConfig[EmsFields::ASSET_CONFIG_QUALITY] ?? 0);
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === $assetConfig[EmsFields::ASSET_CONFIG_TYPE] && !isset($assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE])) {
            $assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE] = 0 === $quality ? 'image/png' : 'image/jpeg';
        }
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === $assetConfig[EmsFields::ASSET_CONFIG_TYPE]) {
            $filename .= 0 === $quality ? '.png' : '.jpg';
        }

        $assetConfig = \array_intersect_key($assetConfig, Config::getDefaults());
        $config = $this->processor->configFactory($hash, $assetConfig);

        return $this->processor->getStreamedResponse($request, $config, $filename, false);
    }

    private function closeSession(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->save();
        }
    }
}

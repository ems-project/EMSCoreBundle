<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\ClientHelperBundle\Controller\AssetController as EmschAssetController;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController extends AbstractController
{
    private Processor $processor;
    private ChannelRepository $channelRepository;
    private EmschAssetController $emschAssetController;
    /** @var array<string, mixed> */
    protected array $assetConfig;

    /**
     * @param array<string, mixed> $assetConfig
     */
    public function __construct(Processor $processor, ChannelRepository $channelRepository, EmschAssetController $emschAssetController, array $assetConfig)
    {
        $this->processor = $processor;
        $this->channelRepository = $channelRepository;
        $this->emschAssetController = $emschAssetController;
        $this->assetConfig = $assetConfig;
    }

    public function assetAction(string $hash, string $hash_config, string $filename, Request $request): Response
    {
        $this->closeSession($request);
        try {
            return $this->processor->getResponse($request, $hash, $hash_config, $filename);
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File %s/%s/%s not found', $hash_config, $hash, $filename));
        }
    }

    public function assetProcessorAction(Request $request, string $processor, string $hash): Response
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
        $quality = \intval($assetConfig[EmsFields::ASSET_CONFIG_QUALITY] ?? 0);
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === $assetConfig[EmsFields::ASSET_CONFIG_TYPE] && !isset($assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE])) {
            $assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE] = 0 === $quality ? 'image/png' : 'image/jpeg';
        }
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === ($assetConfig[EmsFields::ASSET_CONFIG_TYPE] ?? null)) {
            $filename .= 0 === $quality ? '.png' : '.jpg';
        }

        $assetConfig = \array_intersect_key($assetConfig, Config::getDefaults());
        $config = $this->processor->configFactory($hash, $assetConfig);

        return $this->processor->getStreamedResponse($request, $config, $filename, false);
    }

    public function proxyAssetForChannel(Request $request, string $requestPath): Response
    {
        $referer = $request->headers->get('Referer', null);
        if (!\is_string($referer)) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        $parsedReferer = \parse_url($referer);
        if (!\is_array($parsedReferer)) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        $refererPath = $parsedReferer['path'] ?? null;
        if (!\is_string($refererPath)) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }
        $baseUrl = $request->getBaseUrl() ?? '';

        if (\strlen($baseUrl) > 0 && 0 !== \strpos($refererPath, $baseUrl)) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        $refererPathInfo = \substr($refererPath, \strlen($baseUrl));

        if (\preg_match('/^(\\/index\\.php)?\\/bundles\\/(?P<assetsBundles>([a-z\\-0-9_]+))(\\/)?/', $refererPathInfo)) {
            $alias = $this->getLastAliasFromAssetReferer($request, $refererPathInfo);

            if (\is_string($alias)) {
                return $this->emschAssetController->proxyToEnvironmentAlias($requestPath, $alias);
            }

            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        \preg_match(ChannelRegistrar::EMSCO_CHANNEL_PATH_REGEX, $refererPathInfo, $matches);
        if (null === $channelName = $matches['channel'] ?? null) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        try {
            $channel = $this->channelRepository->findRegistered($channelName);
        } catch (\Throwable $e) {
            throw new NotFoundHttpException(\sprintf('Channel %s not found', $channelName));
        }

        $alias = $channel->getAlias();
        if (null === $alias) {
            throw new NotFoundHttpException(\sprintf('Alias for channel %s not found', $channelName));
        }

        $this->saveLastAliasForAssetPath($request, $alias);

        return $this->emschAssetController->proxyToEnvironmentAlias($requestPath, $alias);
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

    private function saveLastAliasForAssetPath(Request $request, string $alias): void
    {
        $request->getSession()->set(\sprintf('EMS_ASSETS_REFERER_%s', $request->getPathInfo()), $alias);
    }

    private function getLastAliasFromAssetReferer(Request $request, string $referer): ?string
    {
        return $request->getSession()->get(\sprintf('EMS_ASSETS_REFERER_%s', $referer), null);
    }
}

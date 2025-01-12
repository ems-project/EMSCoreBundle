<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController extends AbstractController
{
    /**
     * @param array<string, mixed> $assetConfig
     */
    public function __construct(private readonly Processor $processor, private readonly ChannelRepository $channelRepository, protected array $assetConfig)
    {
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
        $quality = \intval($assetConfig[EmsFields::ASSET_CONFIG_QUALITY] ?? 0);
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

    public function proxyAssetForChannel(Request $request, string $requestPath): Response
    {
        @\trigger_error(\sprintf('The route entry %s::proxyAssetForChannel is deprecated, please use EMS\CommonBundle\Controller\FileController::assetInArchive', self::class), E_USER_DEPRECATED);
        $this->closeSession($request);
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
        $baseUrl = $request->getBaseUrl();

        if (\strlen($baseUrl) > 0 && !\str_starts_with($refererPath, $baseUrl)) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        $refererPathInfo = \substr($refererPath, \strlen($baseUrl));

        \preg_match(ChannelRegistrar::EMSCO_CHANNEL_PATH_REGEX, $refererPathInfo, $matches);
        if (null === $channelName = $matches['channel'] ?? null) {
            throw new NotFoundHttpException(\sprintf('File %s not found', $requestPath));
        }

        try {
            $channel = $this->channelRepository->findRegistered($channelName);
        } catch (\Throwable) {
            throw new NotFoundHttpException(\sprintf('Channel %s not found', $channelName));
        }

        $alias = $channel->getAlias();
        if (null === $alias) {
            throw new NotFoundHttpException(\sprintf('Alias for channel %s not found', $channelName));
        }

        if (\preg_match('/\/index\.php$/', $baseUrl, $matches)) {
            $baseUrl = \substr($baseUrl, 0, \strlen($baseUrl) - 10);
        }
        $slugs = [
            $baseUrl,
            'bundles',
            $alias,
            $requestPath,
        ];

        $url = \sprintf('%s?%s', \implode('/', $slugs), $request->getQueryString());
        $response = new RedirectResponse($url, Response::HTTP_FOUND);
        $response->setMaxAge(0);
        $response->mustRevalidate();

        return $response;
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

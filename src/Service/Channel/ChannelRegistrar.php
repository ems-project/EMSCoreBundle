<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\ClientHelperBundle\Helper\Environment\EnvironmentHelper;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\IndexService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ChannelRegistrar
{
    private ChannelRepository $channelRepository;
    private EnvironmentHelper $environmentHelper;
    private LoggerInterface $logger;
    private IndexService $indexService;

    private const EMSCO_CHANNEL_PATH_REGEX = '/^\\/channel\\/(?P<channel>([a-z\\-0-9_]+))(\\/)?/';

    public function __construct(ChannelRepository $channelRepository, EnvironmentHelper $environmentHelper, LoggerInterface $logger, IndexService $indexService)
    {
        $this->channelRepository = $channelRepository;
        $this->environmentHelper = $environmentHelper;
        $this->logger = $logger;
        $this->indexService = $indexService;
    }

    public function register(Request $request): void
    {
        $matches = [];
        \preg_match(self::EMSCO_CHANNEL_PATH_REGEX, $request->getPathInfo(), $matches);

        if (null === $channelName = $matches['channel'] ?? null) {
            return;
        }

        $channel = $this->channelRepository->findRegistered($channelName);

        if (null === $alias = $channel->getAlias()) {
            return;
        }

        if ($this->isAnonymousUser($request) && !$channel->isPublic()) {
            throw new AccessDeniedHttpException('Access restricted to authenticated user');
        }

        $baseUrl = \vsprintf('%s://%s%s/channel/%s', [$request->getScheme(), $request->getHttpHost(), $request->getBasePath(), $channelName]);
        $searchConfig = \json_decode($channel->getOptions()['searchConfig'] ?? '{}', true);
        $attributes = \json_decode($channel->getOptions()['attributes'] ?? null, true);

        if (!$this->indexService->hasIndex($alias)) {
            $this->logger->warning('log.channel.alias_not_found', [
                'alias' => $alias,
                'channel' => $channel,
            ]);
            return;
        }
        $options = [
            Environment::BASE_URL => \sprintf('channel/%s', $channelName),
            Environment::ALIAS_CONFIG => $alias,
            Environment::REGEX_CONFIG => \sprintf('/^%s/', \preg_quote($baseUrl, '/')),
            'search_config' => $searchConfig,
        ];

        if (\is_array($attributes)) {
            $options[Environment::REQUEST_CONFIG] = $attributes;
        }

        $this->environmentHelper->addEnvironment(new Environment($channelName, $options));
    }

    private function isAnonymousUser(Request $request): bool
    {
        return null === $request->getSession()->get('_security_main');
    }

}
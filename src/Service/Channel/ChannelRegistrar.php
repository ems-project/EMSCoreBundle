<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\ClientHelperBundle\Contracts\Environment\EnvironmentHelperInterface;
use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\IndexService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ChannelRegistrar
{
    private ChannelRepository $channelRepository;
    private EnvironmentHelperInterface $environmentHelper;
    private LoggerInterface $logger;
    private IndexService $indexService;
    private string $firewallName;

    public const EMSCO_CHANNEL_PATH_REGEX = '/^(\\/index\\.php)?\\/channel\\/(?P<channel>([a-z\\-0-9_]+))(\\/)?/';

    public function __construct(
        ChannelRepository $channelRepository,
        EnvironmentHelperInterface $environmentHelper,
        LoggerInterface $logger,
        IndexService $indexService,
        string $firewallName
    ) {
        $this->channelRepository = $channelRepository;
        $this->environmentHelper = $environmentHelper;
        $this->logger = $logger;
        $this->indexService = $indexService;
        $this->firewallName = $firewallName;
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

        $baseUrl = \vsprintf('%s://%s%s', [$request->getScheme(), $request->getHttpHost(), $request->getBasePath()]);
        $searchConfig = \json_decode($channel->getOptions()['searchConfig'] ?? '{}', true);
        $attributes = \json_decode($channel->getOptions()['attributes'] ?? null, true);

        if (!$this->indexService->hasIndex($alias)) {
            $this->logger->warning('log.channel.alias_not_found', [
                'alias' => $alias,
                'channel' => $channel->getName(),
            ]);

            return;
        }
        $options = [
            Environment::ALIAS_CONFIG => $alias,
            Environment::ROUTE_PREFIX => \sprintf('channel/%s', $channelName),
            Environment::REGEX_CONFIG => \sprintf('/^%s.*/', \preg_quote($baseUrl, '/')),
            'search_config' => $searchConfig,
        ];

        if (\is_array($attributes)) {
            $options[Environment::REQUEST_CONFIG] = $attributes;
        }

        $this->environmentHelper->addEnvironment($channelName, $options);
    }

    private function isAnonymousUser(Request $request): bool
    {
        return null === $request->getSession()->get('_security_'.$this->firewallName);
    }
}

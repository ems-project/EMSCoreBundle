<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\ClientHelperBundle\Helper\Environment\EnvironmentHelper;
use EMS\CoreBundle\Entity\Channel;
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

    public const EMSCO_CHANNEL_ROUTE_REGEX = '/^emsco\\.channel\\.(?P<environment>([a-z\\-0-9_]+))\\..*/';
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

        if (\count($matches) === 0) {
            return;
        }

        foreach ($this->channelRepository->getAll() as $channel) {
            $channelName = $channel->getName();
            if (null === $channelName) {
                continue;
            }
            $channelAlias = $channel->getAlias();
            if (null === $channelAlias) {
                continue;
            }

            if (
                $channelName === ($matches['channel'] ?? null)
                && !$channel->isPublic()
                && $this->isAnonymousUser($request)) {
                throw new AccessDeniedHttpException('Access restricted to authenticated user');
            }

            $baseUrl = \vsprintf('%s://%s%s/channel/%s', [$request->getScheme(), $request->getHttpHost(), $request->getBasePath(), $channelName]);
            $searchConfig = \json_decode($channel->getOptions()['searchConfig'] ?? '{}', true);
            $attributes = \json_decode($channel->getOptions()['attributes'] ?? null, true);

            if (!$this->indexService->hasIndex($channelAlias)) {
                $this->logger->warning('log.channel.alias_not_found', [
                    'alias' => $channelAlias,
                    'channel' => $channelName,
                ]);
                continue;
            }
            $options = [
                Environment::BASE_URL_CONFIG => \sprintf('channel/%s', $channelName),
                Environment::ALIAS_CONFIG => $channelAlias,
                Environment::ROUTE_PREFIX_CONFIG => Channel::generateChannelRoute($channelName, ''),
                Environment::REGEX_CONFIG => \sprintf('/^%s/', \preg_quote($baseUrl, '/')),
                'search_config' => $searchConfig,
            ];

            if (\is_array($attributes)) {
                $options[Environment::REQUEST_CONFIG] = $attributes;
            }

            $this->environmentHelper->addEnvironment(new Environment($channelName, $options));
        }
    }

    private function isAnonymousUser(Request $request): bool
    {
        return null === $request->getSession()->get('_security_main');
    }

}
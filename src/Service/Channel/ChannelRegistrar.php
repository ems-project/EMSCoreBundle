<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Channel;

use EMS\ClientHelperBundle\Contracts\Environment\EnvironmentHelperInterface;
use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\ClientHelperBundle\Twig\InlineEditExtension;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\IndexService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ChannelRegistrar
{
    public const string ATTRIBUTE_CHANNEL_NAME = '_channel';
    public const string EMSCO_CHANNEL_PATH_REGEX = '/^(\\/index\\.php)?\\/channel\\/(?P<channel>([a-z\\-0-9_]+))(\\/)?/';

    public function __construct(
        private ChannelRepository $channelRepository,
        private EnvironmentHelperInterface $environmentHelper,
        private LoggerInterface $logger,
        private IndexService $indexService,
        private string $firewallName,
        private string $instanceId
    ) {
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

        $options = $channel->getOptions();
        $inlineEditor = $options['inline_editor'] ?? false;
        $prefixInstanceId = $options['prefix_instance_id'] ?? false;
        if (true === $prefixInstanceId) {
            $alias = $this->instanceId.$alias;
        }

        $defaultSearchConfigOption = (isset($options['searchConfig']) && '' !== $options['searchConfig']) ? $options['searchConfig'] : '{}';
        $searchConfig = Json::decode((string) $defaultSearchConfigOption);
        $defaultAttributesOption = (isset($options['attributes']) && '' !== $options['attributes']) ? $options['attributes'] : '{}';
        $attributes = Json::decode((string) $defaultAttributesOption);
        $attributes[self::ATTRIBUTE_CHANNEL_NAME] = $channelName;

        if ($inlineEditor) {
            $attributes[InlineEditExtension::REQUEST_INLINE_EDIT] = true;
        }

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
            Environment::DEFAULT => false,
            'search_config' => $searchConfig,
            Environment::REQUEST_CONFIG => $attributes,
        ];

        $this->environmentHelper->addEnvironment($channelName, $options);
    }

    private function isAnonymousUser(Request $request): bool
    {
        return null === $request->getSession()->get('_security_'.$this->firewallName);
    }
}

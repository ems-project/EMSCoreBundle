<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mercure;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use EMS\Helpers\Standard\Json;
use Lcobucci\JWT\Token\RegisteredClaims;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Update;

use function Symfony\Component\String\u;

class MercureService
{
    private const string TOKEN_EXPIRATION_TIME = '+1 hour';

    public function __construct(
        private readonly HubInterface $mercureHub,
        private readonly UserManager $userManager,
        private readonly string $userUrl,
    ) {
    }

    public function generateToken(): string
    {
        $now = new \DateTimeImmutable('now');

        return $this->getTokenFactory()->create(
            subscribe: $this->getTopics(),
            additionalClaims: [
                RegisteredClaims::ISSUED_AT => $now,
                RegisteredClaims::EXPIRATION_TIME => $now->modify(self::TOKEN_EXPIRATION_TIME),
            ],
        );
    }

    private function getBaseUrl(): string
    {
        if ('' === $this->userUrl) {
            throw new \RuntimeException('EMSCO_URL_USER is not defined');
        }

        return u($this->userUrl)->trimSuffix('/')->toString();
    }

    public function getPublicUrl(): string
    {
        return $this->mercureHub->getPublicUrl();
    }

    private function getTokenFactory(): TokenFactoryInterface
    {
        if (null === $factory = $this->mercureHub->getFactory()) {
            throw new \RuntimeException('No factory was provided');
        }

        return $factory;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function topic(Topic $type, array $params = []): string
    {
        $topic = $this->getBaseUrl().'/'.$type->value;

        if (\count($params) > 0) {
            $topic = \strtr($topic, \array_combine(
                \array_map(static fn ($k) => '{'.$k.'}', \array_keys($params)),
                \array_values($params)
            ));

            if (\preg_match('/\{[^}]+}/', $topic)) {
                throw new \RuntimeException(\sprintf('Replace all params for "%s"', $topic));
            }
        }

        return $topic;
    }

    /** @return string[] */
    public function getTopics(): array
    {
        $userId = $this->userManager->getAuthenticatedUser()->getId();

        return [
            $this->topic(Topic::NOTIFICATIONS),
            $this->topic(Topic::USER, ['id' => $userId]),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public function publish(array $data, string ...$topics): void
    {
        if (0 === \count($topics)) {
            throw new \RuntimeException('No publish topics passed.');
        }

        $this->mercureHub->publish(new Update($topics, Json::encode($data), true));
    }

    /**
     * @param array<mixed> $data
     */
    public function publishForUser(array $data, User|string|null $user = null): void
    {
        $user ??= $this->userManager->getAuthenticatedUser();
        if (\is_string($user)) {
            $user = $this->userManager->getUserByUsername($user);
        }

        if (null === $user) {
            throw new \RuntimeException('User not found.');
        }

        $topic = $this->topic(Topic::USER, ['id' => $user->getId()]);

        $this->publish($data, $topic);
    }
}

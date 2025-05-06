<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Mercure;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use EMS\Helpers\Standard\Json;
use Lcobucci\JWT\Token\RegisteredClaims;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

use function Symfony\Component\String\u;

class MercureService
{
    public const string TOPIC_NOTIFICATIONS = 'notifications';
    private const string TOKEN_EXPIRATION_TIME = '+1 hour';

    public function __construct(
        private readonly HubInterface $mercureHub,
        private readonly UserManager $userManager,
        private readonly string $userUrl,
    ) {
    }

    public function generateToken(): string
    {
        if (null === $factory = $this->mercureHub->getFactory()) {
            throw new \RuntimeException('No factory was provided');
        }

        $now = new \DateTimeImmutable('now');

        return $factory->create(
            subscribe: [
                $this->topic(self::TOPIC_NOTIFICATIONS),
                $this->topic('user/'.$this->userManager->getAuthenticatedUser()->getId()),
            ],
            additionalClaims: [
                RegisteredClaims::ISSUED_AT => $now,
                RegisteredClaims::EXPIRATION_TIME => $now->modify(self::TOKEN_EXPIRATION_TIME),
            ],
        );
    }

    /**
     * @param array<mixed> $data
     */
    public function publish(array $data, string ...$topicNames): void
    {
        $topics = \array_map(fn (string $name) => $this->topic($name), $topicNames);

        $this->mercureHub->publish(new Update($topics, Json::encode($data), true));
    }

    /**
     * @param array<mixed> $data
     */
    public function publishForUser(User $user, array $data): void
    {
        $this->publish($data, 'user/'.$user->getId());
    }

    private function getBaseUrl(): string
    {
        if ('' === $this->userUrl) {
            throw new \RuntimeException('EMSCO_URL_USER is not defined');
        }

        return u($this->userUrl)->trimSuffix('/')->toString();
    }

    private function topic(string $name): string
    {
        return "{$this->getBaseUrl()}/$name";
    }
}

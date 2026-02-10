<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use EMS\CommonBundle\Entity\CreatedModifiedTrait;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class WebhookSubscription implements EntityInterface
{
    use CreatedModifiedTrait;

    private UuidInterface $id;

    private string $endpointUrl;

    private string $secret;

    private ?string $errorMessage = null;

    /** @var string[] */
    private array $events = [];

    private bool $enabled = true;

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->created = new \DateTime();
        $this->modified = new \DateTime();
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    public function setEndpointUrl(string $endpointUrl): void
    {
        $this->endpointUrl = $endpointUrl;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string[] $events
     */
    public function setEvents(array $events): void
    {
        $this->events = $events;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getName(): string
    {
        return $this->getId();
    }
}

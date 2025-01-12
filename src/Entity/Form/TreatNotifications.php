<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

class TreatNotifications
{
    private bool $reject = false;
    private bool $accept = false;

    private ?string $response = null;
    private ?string $publishTo = null;
    /** @var int[] */
    private array $notifications = [];

    public function getPublishTo(): ?string
    {
        return $this->publishTo;
    }

    public function getReject(): bool
    {
        return $this->reject;
    }

    public function getAccept(): bool
    {
        return $this->accept;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    /**
     * @return int[]
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    public function setPublishTo(?string $publishTo): self
    {
        $this->publishTo = $publishTo;

        return $this;
    }

    public function setReject(?bool $reject): self
    {
        $this->reject = $reject ?? false;

        return $this;
    }

    public function setAccept(?bool $accept): self
    {
        $this->accept = $accept ?? false;

        return $this;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @param int[] $notifications
     */
    public function setNotifications(array $notifications): self
    {
        $this->notifications = $notifications;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI\Modal;

class Modal implements \JsonSerializable
{
    /** @var array<int, array<string, string>> */
    private array $messages = [];
    /** @var array<string, mixed> */
    public array $data = [];

    public function __construct(
        public ?string $title = null,
        public ?string $body = null,
        public ?string $footer = null,
    ) {
    }

    public static function forMessage(ModalMessageType $type, string $message, string $title): self
    {
        $modal = new self();
        $modal->title = $title;
        $modal->addMessage($type, $message);

        return $modal;
    }

    /**
     * @return array{modalTitle?: string, modalBody?: string, modalFooter?: string}
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_filter([
            ...$this->data,
            ...[
                'modalTitle' => $this->title,
                'modalBody' => $this->body,
                'modalFooter' => $this->footer,
                'modalMessages' => $this->messages,
            ],
        ]);
    }

    public function addMessage(ModalMessageType $type, string $message): self
    {
        $this->messages[] = [$type->value => $message];

        return $this;
    }
}

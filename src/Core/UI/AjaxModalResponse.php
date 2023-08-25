<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI;

use EMS\Helpers\Standard\Json;

class AjaxModalResponse
{
    private bool $success = true;
    private bool $modalClose = true;
    private ?string $modalTitle = null;

    /** @var array{ string?: string[] } */
    private array $messages = [];
    /** @var array<string, mixed> */
    private array $data = [];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data = []): string
    {
        $response = new self();
        $response->success = true;
        $response->modalClose = true;
        $response->data = $data;

        return $response->getJson();
    }

    public static function warning(string $error): string
    {
        $response = new self();
        $response->modalTitle = 'Error';
        $response->success = false;
        $response->modalClose = false;
        $response->addMessageWarning($error);

        return $response->getJson();
    }

    private function getJson(): string
    {
        return Json::encode(\array_filter([
            ...$this->data,
            ...[
                'success' => $this->success,
                'modalTitle' => $this->modalTitle,
                'modalClose' => $this->modalClose,
                'modalMessages' => $this->messages,
            ],
        ]));
    }

    public function addMessageWarning(string $message): self
    {
        $this->messages[] = ['warning' => $message];

        return $this;
    }
}

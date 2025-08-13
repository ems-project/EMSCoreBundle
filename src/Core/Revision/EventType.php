<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision;

use EMS\CoreBundle\Entity\Environment;

class EventType
{
    private function __construct(
        private readonly bool $migrate = false,
        private readonly bool $finalize = false,
        private readonly bool $publish = false,
        private readonly bool $draft = false,
        private readonly bool $recompute = false,
        private readonly bool $import = false,
        private readonly bool $autoSave = false,
        private readonly bool $savedAsDraft = false,
        private readonly bool $reload = false,
        private readonly ?Environment $target = null,
    ) {
    }

    public static function importEvent(): self
    {
        return new self(migrate: true, finalize: true, import: true);
    }

    public static function recomputeEvent(): self
    {
        return new self(migrate: true, finalize: true, recompute: true);
    }

    public static function finalizeEvent(): self
    {
        return new self(finalize: true);
    }

    public static function publishEvent(Environment $environment): self
    {
        return new self(finalize: true, publish : true, target: $environment);
    }

    public static function autoSaveEvent(): self
    {
        return new self(draft : true, autoSave: true);
    }

    public static function savedAsDraftEvent(): self
    {
        return new self(draft : true, savedAsDraft: true);
    }

    public static function reloadEvent(): self
    {
        return new self(draft : true, reload: true);
    }

    public function isMigrate(): bool
    {
        return $this->migrate;
    }

    public function isFinalize(): bool
    {
        return $this->finalize;
    }

    public function isPublish(): bool
    {
        return $this->publish;
    }

    public function isDraft(): bool
    {
        return $this->draft;
    }

    public function isRecompute(): bool
    {
        return $this->recompute;
    }

    public function isImport(): bool
    {
        return $this->import;
    }

    public function isAutoSave(): bool
    {
        return $this->autoSave;
    }

    public function isSavedAsDraft(): bool
    {
        return $this->savedAsDraft;
    }

    public function isReload(): bool
    {
        return $this->reload;
    }

    public function getTarget(): ?Environment
    {
        return $this->target;
    }
}

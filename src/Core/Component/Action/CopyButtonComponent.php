<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\Action;

use function Symfony\Component\Translation\t;

class CopyButtonComponent
{
    public ?string $text = null;
    public ?string $target = null;
    public string $label;
    public string $icon = 'fa fa-copy';
    private bool $empty = false;

    public function __construct()
    {
        $this->label = t('action.copy', [], 'emsco—core')->getMessage();
    }

    public function mount(
        ?string $text = null,
        ?string $target = null,
    ): void {
        if (null === $text && null === $target) {
            $this->empty = true;
        } elseif (null !== $text && null !== $target) {
            throw new \InvalidArgumentException('"text" and "target" cannot both be specified.');
        }

        if (null !== $target && '' === \trim($target)) {
            throw new \InvalidArgumentException('The "target" must be a non-empty HTML element ID.');
        }

        $this->text = $text;
        $this->target = $target;
    }

    public function isEmpty(): bool
    {
        return $this->empty;
    }
}

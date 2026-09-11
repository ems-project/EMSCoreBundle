<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum TaskStatus: string implements TranslatableInterface
{
    case PROGRESS = 'progress';
    case REJECTED = 'rejected';
    case APPROVED = 'approved';
    case PLANNED = 'planned';
    case COMPLETED = 'completed';

    public function getColor(): string
    {
        return match ($this) {
            self::PROGRESS => 'blue',
            self::PLANNED => 'gray',
            self::COMPLETED, self::APPROVED => 'green',
            self::REJECTED => 'red',
        };
    }

    public function getCssClassIcon(): string
    {
        $icon = match ($this) {
            self::PROGRESS => 'fa fa-ticket',
            self::PLANNED => 'fa fa-hourglass-o',
            self::COMPLETED => 'fa fa-paper-plane',
            self::REJECTED => 'fa fa-close',
            self::APPROVED => 'fa fa-check',
        };

        return $icon.' '.$this->getCssClassText();
    }

    public function getCssClassLabel(): string
    {
        return match ($this) {
            self::PROGRESS => 'label-primary',
            self::PLANNED => 'label-default',
            self::COMPLETED, self::APPROVED => 'label-success',
            self::REJECTED => 'label-danger',
        };
    }

    public function getCssClassText(): string
    {
        return match ($this) {
            self::PROGRESS => 'text-primary',
            self::PLANNED => 'text-muted',
            self::COMPLETED, self::APPROVED => 'text-success',
            self::REJECTED => 'text-danger',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::PROGRESS => t('task.status.progress', [], 'emsco-core')->trans($translator),
            self::PLANNED => t('task.status.planned', [], 'emsco-core')->trans($translator),
            self::COMPLETED => t('task.status.completed', [], 'emsco-core')->trans($translator),
            self::APPROVED => t('task.status.approved', [], 'emsco-core')->trans($translator),
            self::REJECTED => t('task.status.rejected', [], 'emsco-core')->trans($translator),
        };
    }
}

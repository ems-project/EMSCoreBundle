<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Task;

enum TaskStatus: string
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
}

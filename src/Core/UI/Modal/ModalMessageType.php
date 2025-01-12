<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\UI\Modal;

enum ModalMessageType: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
}

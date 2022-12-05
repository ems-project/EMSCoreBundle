<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\User\UserManager;

abstract class AbstractUserCommand extends AbstractCommand
{
    public function __construct(protected UserManager $userManager)
    {
        parent::__construct();
    }
}

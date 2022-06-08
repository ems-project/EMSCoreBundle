<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Core\User\UserManager;

abstract class AbstractUserCommand extends AbstractCommand
{
    protected UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        parent::__construct();
        $this->userManager = $userManager;
    }
}

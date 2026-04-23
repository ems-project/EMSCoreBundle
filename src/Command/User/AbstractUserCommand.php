<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Command\AbstractCoreCommand;
use EMS\CoreBundle\Core\User\UserManager;

abstract class AbstractUserCommand extends AbstractCoreCommand
{
    public function __construct(protected UserManager $userManager)
    {
        parent::__construct();
    }
}

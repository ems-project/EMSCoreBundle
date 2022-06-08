<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Commands;
use FOS\UserBundle\Util\UserManipulator;
use Symfony\Component\Console\Output\OutputInterface;

class PromoteUserCommand extends RoleCommand
{
    protected static $defaultName = Commands::USER_PROMOTE;

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Promotes a user by adding a role')
            ->setHelp(<<<'EOT'
The <info>fos:user:promote</info> command promotes a user by adding a role

  <info>php %command.full_name% matthieu ROLE_CUSTOM</info>
  <info>php %command.full_name% --super matthieu</info>
EOT
            );
    }

    protected function executeRoleCommand(UserManipulator $manipulator, OutputInterface $output, string $username, bool $super, string $role): void
    {
        if ($super) {
            $manipulator->promote($username);
            $output->writeln(\sprintf('User "%s" has been promoted as a super administrator. This change will not apply until the user logs out and back in again.', $username));
        } else {
            if ($manipulator->addRole($username, $role)) {
                $output->writeln(\sprintf('Role "%s" has been added to user "%s". This change will not apply until the user logs out and back in again.', $role, $username));
            } else {
                $output->writeln(\sprintf('User "%s" did already have "%s" role.', $username, $role));
            }
        }
    }
}
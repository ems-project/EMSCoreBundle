<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Commands;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: Commands::USER_DEMOTE,
    description: 'Demote a user by removing a role.',
    hidden: false
)]
class DemoteUserCommand extends RoleCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setHelp(
                <<<'EOT'
                    The <info>emsco:user:demote</info> command demotes a user by removing a role

                      <info>php %command.full_name% matthieu ROLE_CUSTOM</info>
                      <info>php %command.full_name% --super matthieu</info>
                    EOT
            );
    }

    #[\Override]
    protected function executeRoleCommand(string $username, bool $super, string $role): void
    {
        if ($super) {
            $this->userManager->updateSuperAdmin($username, false);
            $this->io->success(\sprintf('User "%s" has been demoted as a simple user. This change will not apply until the user logs out and back in again.', $username));
        } else {
            if ($this->userManager->updateRoleRemove($username, $role)) {
                $this->io->success(\sprintf('Role "%s" has been removed from user "%s". This change will not apply until the user logs out and back in again.', $role, $username));
            } else {
                $this->io->warning(\sprintf('User "%s" did not have "%s" role.', $username, $role));
            }
        }
    }
}

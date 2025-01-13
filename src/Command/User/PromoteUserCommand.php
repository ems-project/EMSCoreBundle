<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Commands;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: Commands::USER_PROMOTE,
    description: 'Promotes a user by adding a role.',
    hidden: false
)]
class PromoteUserCommand extends RoleCommand
{
    #[\Override]
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setHelp(
                <<<'EOT'
                    The <info>emsco:user:promote</info> command promotes a user by adding a role

                      <info>php %command.full_name% matthieu ROLE_CUSTOM</info>
                      <info>php %command.full_name% --super matthieu</info>
                    EOT
            );
    }

    #[\Override]
    protected function executeRoleCommand(string $username, bool $super, string $role): void
    {
        if ($super) {
            $this->userManager->updateSuperAdmin($username, true);

            $this->io->success(\sprintf('User "%s" has been promoted as a super administrator. This change will not apply until the user logs out and back in again.', $username));
        } else {
            if ($this->userManager->updateRoleAdd($username, $role)) {
                $this->io->success(\sprintf('Role "%s" has been added to user "%s". This change will not apply until the user logs out and back in again.', $role, $username));
            } else {
                $this->io->warning(\sprintf('User "%s" did already have "%s" role.', $username, $role));
            }
        }
    }
}

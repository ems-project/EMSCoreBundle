<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::USER_UPDATE_OPTION,
    description: 'Update a user option.',
    hidden: false
)]
class UpdateUserOptionCommand extends AbstractUserCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('option', InputArgument::REQUIRED, \implode('|', UserOptions::ALL_MEMBERS))
            ->addArgument('value', InputArgument::REQUIRED, 'value for updating')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'use wildcard % (%@example.dev)')
            ->setHelp(<<<'EOT'
The <info>emsco:user:update-option</info> command changes an option of a user(s):

  Enable "simplified_ui" for all users
  <info>php %command.full_name% simplified_ui true</info>

  Enable "allowed_configure_wysiwyg" for all users
  <info>php %command.full_name% allowed_configure_wysiwyg true</info>

  Set country "Belgium" for all users with a .be email address
  <info>php %command.full_name% custom_options '{"country":"Belgium"}' --email='%.be'</info>

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - User - Update option');
        $option = $this->getArgumentString('option');

        try {
            match ($option) {
                UserOptions::SIMPLIFIED_UI => $this->updateBool(UserOptions::SIMPLIFIED_UI),
                UserOptions::ALLOWED_CONFIGURE_WYSIWYG => $this->updateBool(UserOptions::ALLOWED_CONFIGURE_WYSIWYG),
                UserOptions::CUSTOM_OPTIONS => $this->updateCustomOptions(),
                default => throw new \RuntimeException(\sprintf('Invalid option "%s" passed', $option)),
            };

            return self::EXECUTE_SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }
    }

    private function updateBool(string $option): void
    {
        $value = 'true' === $this->getArgumentString('value');

        foreach ($this->updateUsersOptions() as $userOptions) {
            $userOptions[$option] = $value;
        }
    }

    private function updateCustomOptions(): void
    {
        $customOptions = Json::decode($this->getArgumentString('value'));

        foreach ($this->updateUsersOptions() as $userOptions) {
            $userOptions[UserOptions::CUSTOM_OPTIONS] = \array_merge_recursive(
                $userOptions[UserOptions::CUSTOM_OPTIONS],
                $customOptions
            );
        }
    }

    /**
     * @return \Generator<UserOptions>
     */
    private function updateUsersOptions(): \Generator
    {
        $email = $this->getOptionStringNull('email');
        $countFindAll = $this->userManager->countFindAll($email);
        $progressBar = $this->io->createProgressBar($countFindAll['count']);

        foreach ($countFindAll['results'] as $user) {
            $userOptions = $user->getUserOptions();
            yield $userOptions;
            $user->setUserOptions($userOptions);
            $this->userManager->update($user);
        }

        $progressBar->finish();
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use EMS\CoreBundle\Commands;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: Commands::USER_DEACTIVATE,
    description: 'Deactivate a user.',
    hidden: false
)]
class DeactivateUserCommand extends AbstractUserCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('username', InputArgument::REQUIRED, 'The username'),
            ])
            ->setHelp(<<<'EOT'
The <info>emsco:user:deactivate</info> command deactivates a user (will not be able to log in)

  <info>php %command.full_name% matthieu</info>
EOT
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $username = $this->getArgumentString('username');

            $this->userManager->updateEnabled($username, false);
            $this->io->success(\sprintf('User "%s" has been deactivated.', $username));

            return self::EXECUTE_SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (!$input->getArgument('username')) {
            $question = new Question('Please choose a username:');
            $question->setValidator(function ($username) {
                if (empty($username)) {
                    throw new \Exception('Username can not be empty');
                }

                return $username;
            });
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $answer = $questionHelper->ask($input, $output, $question);

            $input->setArgument('username', $answer);
        }
    }
}

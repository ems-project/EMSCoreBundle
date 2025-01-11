<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class RoleCommand extends AbstractUserCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('username', InputArgument::REQUIRED, 'The username'),
                new InputArgument('role', InputArgument::OPTIONAL, 'The role'),
                new InputOption('super', null, InputOption::VALUE_NONE, 'Instead specifying role, use this to quickly add the super administrator role'),
            ]);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $username = $this->getArgumentString('username');
            $role = $this->getArgumentString('role');
            $super = $this->getOptionBool('super');

            if ($super && $role) {
                throw new \InvalidArgumentException('You can pass either the role or the --super option (but not both simultaneously).');
            }

            if (!$super && !$role) {
                throw new \RuntimeException('Not enough arguments.');
            }

            $this->executeRoleCommand($username, $super, $role);

            return self::EXECUTE_SUCCESS;
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }
    }

    abstract protected function executeRoleCommand(string $username, bool $super, string $role): void;

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $questions = [];

        if (!$input->getArgument('username')) {
            $question = new Question('Please choose a username:');
            $question->setValidator(function ($username) {
                if (empty($username)) {
                    throw new \Exception('Username can not be empty');
                }

                return $username;
            });
            $questions['username'] = $question;
        }

        if ((true !== $input->getOption('super')) && !$input->getArgument('role')) {
            $question = new Question('Please choose a role:');
            $question->setValidator(function ($role) {
                if (empty($role)) {
                    throw new \Exception('Role can not be empty');
                }

                return $role;
            });
            $questions['role'] = $question;
        }

        foreach ($questions as $name => $question) {
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $answer = $questionHelper->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}

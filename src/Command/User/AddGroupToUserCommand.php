<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\User;

use Doctrine\ORM\EntityNotFoundException;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\EntityInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: Commands::USER_ADD_GROUP,
    description: 'Specify a user Group.',
    hidden: false
)]
class AddGroupToUserCommand extends AbstractUserCommand
{
    public function __construct(
        UserManager $userManager,
        protected GroupManager $groupManager
    ) {
        parent::__construct($userManager);
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('username', InputArgument::REQUIRED, 'The username'),
                new InputArgument('group', InputArgument::REQUIRED, 'The group to add'),
            ])
            ->setHelp(
                <<<'EOT'
                    The <info>emsco:user:add-group</info> command adds a group to a user:

                      <info>php %command.full_name% matthieu admins</info>

                    This interactive shell will first ask you for a group if not provided.

                    You can alternatively specify the group as a second argument:

                      <info>php %command.full_name% matthieu admins</info>

                    EOT
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $username = $this->getArgumentString('username');
            $group = $this->getArgumentString('group');

            $user = $this->userManager->getUserByUsername($username);
            $userGroup = $this->groupManager->getByItemName($group);

            if (!$userGroup instanceof EntityInterface) {
                throw new EntityNotFoundException();
            }
            if (null !== $user) {
                $user->setGroup($userGroup);
                $this->userManager->update($user);

                $this->io->success(\sprintf('Group "%s" has been added to user "%s".', $group, $username));

                return self::EXECUTE_SUCCESS;
            }

            return self::EXECUTE_ERROR;
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $questions = [];
        $this->io->title('Add a group to a user');
        if (!$input->getArgument('username')) {
            $question = new Question('Please give the username:');
            $question->setValidator(function ($username) {
                if (empty($username)) {
                    throw new \Exception('Username cannot be empty');
                }

                return $username;
            });
            $questions['username'] = $question;
        }

        if (!$input->getArgument('group')) {
            $question = new Question('Please enter the group to add:');
            $question->setValidator(function ($group) {
                if (empty($group)) {
                    throw new \Exception('Group cannot be empty');
                }

                return $group;
            });
            $questions['group'] = $question;
        }

        foreach ($questions as $name => $question) {
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $answer = $questionHelper->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}

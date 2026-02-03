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
    name: Commands::USER_REMOVE_GROUP,
    description: 'Remove a group from a user.',
    hidden: false,
    help: <<<TXT
        The <info>emsco:user:delete-group</info> command removes the group from a user:

          <info>php %command.full_name% matthieu</info>

        This interactive shell will ask you for the username if not provided.

        The group associated with the user will be removed automatically.

        TXT
)]
class RemoveGroupFromUserCommand extends AbstractUserCommand
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
            ]);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $username = $this->getArgumentString('username');

            $user = $this->userManager->getUserByUsername($username);

            if (null !== $user) {
                $group = $user->getGroup();
                if (null !== $group) {
                    $userGroup = $this->groupManager->getByItemName($group->getName());

                    if (!$userGroup instanceof EntityInterface) {
                        throw new EntityNotFoundException();
                    }
                }

                $user->setGroup(null);
                $this->userManager->update($user);

                $this->io->success(\sprintf('Group "%s" has been removed from user "%s".', $group, $username));

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
        $this->io->title('Remove a group from a user');

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

        foreach ($questions as $name => $question) {
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $answer = $questionHelper->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}

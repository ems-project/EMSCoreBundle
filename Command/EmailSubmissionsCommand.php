<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Symfony\Component\Console\Command\Command;

class EmailSubmissionsCommand extends Command implements CommandInterface
{
    /** @var FormSubmissionService */
    protected $formSubmissionService;

    /** @var LoggerInterface */
    protected $logger;

    protected static $defaultName = 'ems:submissions:email';

    public function __construct(FormSubmissionService $formSubmissionService, LoggerInterface $logger)
    {
        $this->formSubmissionService = $formSubmissionService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Send a list of all submissions to the specified email address')
            ->addArgument('formInstance', InputArgument::REQUIRED, 'Which form submissions do you want to list?')
            ->addArgument(
                'emails',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'To which email addresses do you want to send the list?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $formInstance = $input->getArgument('formInstance');
        $emails = $input->getArgument('emails');

        $submissions = $this->formSubmissionService->getFormInstanceSubmissions($formInstance);

        $this->formSubmissionService->mailSubmissions($submissions, $formInstance, $emails);

        $this->logger->notice(\sprintf('Submission list for %s was sent', $formInstance));
        $output->writeln(\sprintf('Submission list for %s was sent', $formInstance));

        return 0;
    }
}
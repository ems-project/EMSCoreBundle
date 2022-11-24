<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EmailSubmissionsCommand extends Command implements CommandInterface
{
    protected FormSubmissionService $formSubmissionService;
    protected LoggerInterface $logger;
    protected MailerService $mailerService;

    protected static $defaultName = 'ems:submissions:email';

    private const TITLE = 'Form submissions';

    public function __construct(FormSubmissionService $formSubmissionService, LoggerInterface $logger, MailerService $mailerService)
    {
        $this->formSubmissionService = $formSubmissionService;
        $this->logger = $logger;
        $this->mailerService = $mailerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Send a list of form submissions to the specified email address or addresses')
            ->addArgument(
                'emails',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'To which email addresses do you want to send the list?'
            )
            ->addOption(
                'formInstance',
                null,
                InputOption::VALUE_OPTIONAL,
                'From which form instance do you want to mail submissions? Defaults to all.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $emails = (array) $input->getArgument('emails');
        $formInstance = \strval($input->getOption('formInstance'));

        $submissions = $this->formSubmissionService->getFormSubmissions($formInstance);

        $body = $this->formSubmissionService->generateMailBody($submissions);

        $this->mailerService->send($emails, self::TITLE, $body);

        $this->logger->notice('Submission list was sent');
        $output->writeln('Submission list was sent');

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Submission;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SubmissionEmailCommand extends AbstractCommand
{
    private FormSubmissionService $formSubmissionService;
    private MailerService $mailerService;

    protected static $defaultName = Commands::SUBMISSION_EMAIL;

    private const TITLE = 'Form submissions';

    public function __construct(FormSubmissionService $formSubmissionService, MailerService $mailerService)
    {
        parent::__construct();
        $this->formSubmissionService = $formSubmissionService;
        $this->mailerService = $mailerService;
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

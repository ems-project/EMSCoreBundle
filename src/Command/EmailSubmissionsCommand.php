<?php

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::SUBMISSIONS_EMAIL,
    description: 'Send a list of form submissions to the specified email address or addresses.',
    hidden: false,
    aliases: ['ems:submissions:email']
)]
class EmailSubmissionsCommand extends Command implements CommandInterface
{
    private const string TITLE = 'Form submissions';

    public function __construct(protected FormSubmissionService $formSubmissionService, protected LoggerInterface $logger, protected MailerService $mailerService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
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

    #[\Override]
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

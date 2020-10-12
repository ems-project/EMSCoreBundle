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
use EMS\CoreBundle\Service\MailerService;

class EmailSubmissionsCommand extends Command implements CommandInterface
{
    /** @var FormSubmissionService */
    protected $formSubmissionService;

    /** @var LoggerInterface */
    protected $logger;

    /** @var MailerService */
    protected $mailerService;

    protected static $defaultName = 'ems:submissions:email';

    const TITLE = 'Form submissions';

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
            ->addArgument('templateId', InputArgument::REQUIRED, 'Which template (id) do you want to use?')
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
        $templateId = \strval($input->getArgument('templateId'));
        $emails = (array) $input->getArgument('emails');
        $formInstance = \strval($input->getOption('formInstance'));

        if ($formInstance) {
            $submissions = $this->formSubmissionService->getFormInstanceSubmissions($formInstance);
        } else {
            $submissions = $this->formSubmissionService->getAllFormSubmissions();
        }

        $body = $this->formSubmissionService->generateMailBody($submissions, $templateId);

        $this->mailerService->sendMail($emails, self::TITLE, $body);

        $this->logger->notice('Submission list was sent');
        $output->writeln('Submission list was sent');

        return 0;
    }
}

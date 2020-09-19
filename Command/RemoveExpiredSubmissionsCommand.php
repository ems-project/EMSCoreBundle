<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;

class RemoveExpiredSubmissionsCommand extends EmsCommand
{
    /** @var FormSubmissionService */
    protected $formSubmissionService;

    /** @var LoggerInterface */
    protected $logger;

    protected static $defaultName = 'ems:submissions:remove-expired';

    public function __construct(Client $client, FormSubmissionService $formSubmissionService, LoggerInterface $logger)
    {
        $this->formSubmissionService = $formSubmissionService;
        $this->logger = $logger;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->setDescription('Removes all form submissions that passed their deadline');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $removedCount = $this->formSubmissionService->removeExpiredSubmissions();

        $this->logger->notice(\sprintf('%d submissions were removed', $removedCount));
        $output->writeln(\sprintf('%d submissions were removed', $removedCount));
    }
}

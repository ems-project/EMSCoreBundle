<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;

class RemoveExpiredSubmissionsCommand extends EmsCommand
{
    /** @var LoggerInterface */
    protected $logger;

    protected static $defaultName = 'ems:submissions:remove-expired';
    /**
     * @var FormSubmissionService
     */
    private $formSubmissionService;

    public function __construct(LoggerInterface $logger, Client $client, FormSubmissionService $formSubmissionService)
    {
        $this->logger = $logger;
        $this->formSubmissionService = $formSubmissionService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this->setDescription('Removes all form submissions that passed their deadline');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formSubmissionService->removeExpiredSubmissions();
        dd('OK');
    }
}

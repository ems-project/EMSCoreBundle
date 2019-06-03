<?php

// src/EMS/CoreBundle/Command/GreetCommand.php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class AssetClearCacheCommand extends EmsCommand
{
    /** @var FileService */
    protected $fileService;


    public function __construct(LoggerInterface $logger, Client $client, FileService $fileService)
    {
        $this->fileService = $fileService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this
            ->setName('ems:asset:clear-cache')
            ->setDescription('Clear storage service\'s caches')
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'All storage services will be cleared'
            );
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->formatStyles($output);

        if (! $input->getOption('all')) {
            /**@var QuestionHelper $helper*/
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the storage service to clear',
                array_merge($this->fileService->getStorages(), ['All']),
                0
            );
            $question->setErrorMessage('Service %s is invalid.');

            $service = $helper->ask($input, $output, $question);


            if ($service != 'All') {
                $serviceId = array_search($service, $this->fileService->getStorages());
                $output->writeln('You have just selected: '.$service);
                $this->fileService->getStorages()[$serviceId]->clearCache();
                return;
            }
        }

        // create a new progress bar
        $progress = new ProgressBar($output, count($this->fileService->getStorages()));
        // start and displays the progress bar
        $progress->start();


        /**@var StorageInterface $storage */
        foreach ($this->fileService->getStorages() as $storage) {
            $storage->clearCache();
            $progress->advance();
        }


        $progress->finish();
        $output->writeln('');
        $output->writeln('<comment>Elasticms assets caches cleared</comment>');
    }
}

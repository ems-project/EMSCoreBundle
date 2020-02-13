<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\TransformContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TransformContentTypeCommand extends EmsCommand
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var ContentTypeService */
    protected $contentTypeService;

    /** @var TransformContentTypeService */
    protected $transformContentTypeService;

    protected static $defaultName = 'ems:contenttype:transform';

    public function __construct(LoggerInterface $logger, Client $client, ContentTypeService $contentTypeService, TransformContentTypeService $transformContentTypeService)
    {
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->transformContentTypeService = $transformContentTypeService;

        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this
            ->setDescription('Transform the Content Type defined')
            ->addArgument(
                'contentTypeName',
                InputArgument::REQUIRED,
                'Content Type name'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $contentTypeName = $input->getArgument('contentTypeName');
        $contentType = $this->contentTypeService->getByName($contentTypeName);

        if (!$contentType) {
            $output->writeln('<error>Content type ' . $contentTypeName . ' not found</error>');
            return -1;
        }

        $total = $this->transformContentTypeService->getTotal($contentType);
        $hits = $this->transformContentTypeService->transform($contentType);

        $output->writeln('<comment>Start transformation of ' . $contentType->getPluralName() . '</comment>');
        $output->writeln('');

        $progress = new ProgressBar($output, $total);
        $progress->start();

        foreach ($hits as $hit) {
            $progress->advance();
        }

        $progress->finish();

        $output->writeln('');
        $output->writeln('');
        $output->writeln('<comment>Transformation done</comment>');

        return 0;
    }
}

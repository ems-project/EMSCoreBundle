<?php

namespace EMS\CoreBundle\Command;

use Elasticsearch\Client;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\TransformContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TransformContentTypeCommand extends Command
{
    protected static $defaultName = 'ems:contenttype:transform';

    /** @var LoggerInterface */
    protected $logger;

    /** @var ContentTypeService */
    protected $contentTypeService;

    /** @var TransformContentTypeService */
    protected $transformContentTypeService;

    /** @var SymfonyStyle */
    private $io;

    const ARGUMENT_CONTENTTYPE_NAME = 'contentTypeName';
    const OPTION_STRICT = 'strict';

    public function __construct(LoggerInterface $logger, ContentTypeService $contentTypeService, TransformContentTypeService $transformContentTypeService)
    {
        $this->logger = $logger;
        $this->contentTypeService = $contentTypeService;
        $this->transformContentTypeService = $transformContentTypeService;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Transform the Content Type defined')
            ->addArgument(
                self::ARGUMENT_CONTENTTYPE_NAME,
                InputArgument::REQUIRED,
                'Content Type name'
            )
            ->addOption(
                self::OPTION_STRICT,
                null,
                InputOption::VALUE_NONE,
                'If set, the check failed will throw an exception'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Transform content-type');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->info('Interact with TransformContentType command');

        $this->io->section('Check environment name argument');
        $this->checkContentTypeNameArgument($input);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Execute the TransformContentType command');

        $contentTypeName = $input->getArgument('contentTypeName');
        $contentType = $this->contentTypeService->getByName($contentTypeName);

        $total = $this->transformContentTypeService->getTotal($contentType);
        $hits = $this->transformContentTypeService->transform($contentType);

        $this->io->note(\sprintf('Start transformation of "%s"', $contentType->getPluralName()));

        $this->io->progressStart($total);
        foreach ($hits as $hit) {
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $this->io->success(\sprintf('Transformation of "%s" content type done', $contentType->getPluralName()));
        return 0;
    }

    private function checkContentTypeNameArgument(InputInterface $input)
    {
        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENTTYPE_NAME);
        if (null === $contentTypeName) {
            $message = 'The content type name is not provided';
            $this->setContentTypeNameArgument($input, $message);
        }

        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (false === $contentType) {
            $message = \sprintf('The content type "%s" not found', $contentTypeName);
            $this->setContentTypeNameArgument($input, $message);
            $this->checkContentTypeNameArgument($input);
            return;
        }
    }

    private function setContentTypeNameArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $contentTypeName = $this->io->choice('Select an existing content type', $this->contentTypeService->getAllNames());
        $input->setArgument(self::ARGUMENT_CONTENTTYPE_NAME, $contentTypeName);
    }
}

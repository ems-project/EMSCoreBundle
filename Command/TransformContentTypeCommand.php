<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\TransformContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class TransformContentTypeCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'ems:contenttype:transform';
    /** @var LoggerInterface */
    protected $logger;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var TransformContentTypeService */
    protected $transformContentTypeService;
    /** @var SymfonyStyle */
    private $io;
    /** @var string */
    private $user;

    const ARGUMENT_CONTENTTYPE_NAME = 'contentTypeName';
    const ARGUMENT_USER = 'user';
    const OPTION_STRICT = 'strict';
    const DEFAULT_USER = 'TRANSFORM_CONTENT';

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
            ->addArgument(
                self::ARGUMENT_USER,
                InputArgument::OPTIONAL,
                'The user name: the user must correspond to the lock user.',
                self::DEFAULT_USER
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
        $this->io->section('Check environment name argument');
        $this->checkContentTypeArgument($input);
        $this->checkUserArgument($input);

        $this->user = $input->getArgument(self::ARGUMENT_USER);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Execute the TransformContentType command');

        $contentTypeName = $input->getArgument('contentTypeName');
        $contentType = $this->contentTypeService->getByName($contentTypeName);

        $total = $this->transformContentTypeService->getTotal($contentType);
        $hits = $this->transformContentTypeService->transform($contentType, $this->user);

        $this->io->note(\sprintf('Start transformation of "%s"', $contentType->getPluralName()));

        $this->io->progressStart($total);
        foreach ($hits as $hit) {
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $this->io->success(\sprintf('Transformation of "%s" content type done', $contentType->getPluralName()));
        return 0;
    }

    private function checkContentTypeArgument(InputInterface $input): void
    {
        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENTTYPE_NAME);
        if (null === $contentTypeName) {
            $message = 'The content type name is not provided';
            $this->setContentTypeArgument($input, $message);
            return;
        }

        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (false === $contentType) {
            $message = \sprintf('The content type "%s" not found', $contentTypeName);
            $this->setContentTypeArgument($input, $message);
            $this->checkContentTypeArgument($input);
            return;
        }
    }

    private function setContentTypeArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $contentTypeName = $this->io->choice('Select an existing content type', $this->contentTypeService->getAllNames());
        $input->setArgument(self::ARGUMENT_CONTENTTYPE_NAME, $contentTypeName);
    }

    private function checkUserArgument(InputInterface $input): void
    {
        $user = $input->getArgument(self::ARGUMENT_USER);
        if (null === $user) {
            $message = 'The user name is not provided';
            $this->setUserArgument($input, $message);
            return;
        }
    }

    private function setUserArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $user = $this->io->ask('Insert a user name: the user must correspond to the "lock user"');
        $input->setArgument(self::ARGUMENT_USER, $user);
    }
}

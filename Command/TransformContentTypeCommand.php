<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
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
    /** @var ContentType */
    private $contentType;
    /** @var string */
    private $user;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const ARGUMENT_USER = 'user';
    private const OPTION_STRICT = 'strict';
    private const DEFAULT_USER = 'TRANSFORM_CONTENT';

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
            ->setDescription('Transform the content-type defined')
            ->addArgument(
                self::ARGUMENT_CONTENT_TYPE,
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

        $this->contentType = $this->contentTypeService->getByName($input->getArgument(self::ARGUMENT_CONTENT_TYPE));
        $this->user = $input->getArgument(self::ARGUMENT_USER);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Execute the TransformContentType command');

        $total = $this->transformContentTypeService->getTotal($this->contentType);
        $hits = $this->transformContentTypeService->transform($this->contentType, $this->user);

        $this->io->note(\sprintf('Start transformation of "%s"', $this->contentType->getPluralName()));

        $this->io->progressStart($total);
        foreach ($hits as $hit) {
            $this->io->progressAdvance();
        }
        $this->io->progressFinish();

        $this->io->success(\sprintf('Transformation of "%s" content type done', $this->contentType->getPluralName()));
        return 0;
    }

    private function checkContentTypeArgument(InputInterface $input): void
    {
        if (null === $input->getArgument(self::ARGUMENT_CONTENT_TYPE)) {
            $message = 'The content type name is not provided';
            $this->setContentTypeArgument($input, $message);
        }

        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!is_string($contentTypeName)) {
            throw new \RuntimeException('Content type name as to be a string');
        }

        if (false === $this->contentTypeService->getByName($contentTypeName)) {
            $message = \sprintf('The content type "%s" not found', $contentTypeName);
            $this->setContentTypeArgument($input, $message);
            $this->checkContentTypeArgument($input);
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
        $input->setArgument(self::ARGUMENT_CONTENT_TYPE, $contentTypeName);
    }

    private function checkUserArgument(InputInterface $input): void
    {
        if (null === $input->getArgument(self::ARGUMENT_USER)) {
            $message = 'The user name is not provided';
            $this->setUserArgument($input, $message);
        }
    }

    private function setUserArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $user = $this->io->ask(
            'Insert a user name: the user must correspond to the "lock user"',
            null,
            function ($user) {
                if (empty($user)) {
                    throw new \RuntimeException('User cannot be empty.');
                }
                return $user;
            }
        );
        $input->setArgument(self::ARGUMENT_USER, $user);
    }
}

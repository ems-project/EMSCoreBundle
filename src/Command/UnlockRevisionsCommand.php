<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UnlockRevisionsCommand extends Command
{
    /** @var LoggerInterface */
    private $logger;
    /** @var DataService */
    private $dataService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var SymfonyStyle */
    private $io;
    /** @var string */
    private $user;
    /** @var ContentType */
    private $contentType;
    /** @var bool */
    private $all;

    public const name = 'ems:revisions:unlock';
    protected static $defaultName = self::name;

    private const ARGUMENT_USER = 'user';
    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const OPTION_ALL = 'all';
    private const OPTION_STRICT = 'strict';

    public function __construct(LoggerInterface $logger, DataService $dataService, ContentTypeService $contentTypeService)
    {
        $this->logger = $logger;
        $this->dataService = $dataService;
        $this->contentTypeService = $contentTypeService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Unlock all content-types revisions.')
            ->addArgument(
                self::ARGUMENT_USER,
                InputArgument::REQUIRED,
                'The user name: the user must correspond to the lock user.'
            )
            ->addArgument(
                self::ARGUMENT_CONTENT_TYPE,
                InputArgument::OPTIONAL,
                'The content-type target name. If you need to target ALL the content-types, don\'t use this argument but add the "--all" option.'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'If set, all the content-types will be targeted.'
            )
            ->addOption(
                self::OPTION_STRICT,
                null,
                InputOption::VALUE_NONE,
                'If set, an interact check failed will throw an exception.'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Unlock revisions');
        $this->all = (true === $input->getOption(self::OPTION_ALL)) ?? false;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->io->section('Check arguments');
        $this->checkUserArgument($input);
        $this->checkContentTypeArgument($input);

        $user = $input->getArgument(self::ARGUMENT_USER);
        if (!\is_string($user)) {
            throw new \RuntimeException('Unexpected user name');
        }
        $this->user = $user;

        if (!$this->all) {
            $name = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
            if (!\is_string($name)) {
                throw new \RuntimeException('Unexpected content type name');
            }
            $contentType = $this->contentTypeService->getByName($name);
            if (false === $contentType) {
                throw new \RuntimeException('Content type not found');
            }
            $this->contentType = $contentType;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if ($this->all) {
                $count = $this->dataService->unlockAllRevisions($this->user);
            } else {
                $count = $this->dataService->unlockRevisions($this->contentType, $this->user);
            }
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());

            return -1;
        }

        $this->io->success(\sprintf('%s revisions have been unlocked', $count));

        return 0;
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

    private function checkContentTypeArgument(InputInterface $input): void
    {
        if ($this->all) {
            return;
        }

        if (null === $input->getArgument(self::ARGUMENT_CONTENT_TYPE)) {
            $message = 'The content type name is not provided';
            $this->setContentTypeArgument($input, $message);
        }

        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Content Type name as to be a string');
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
}

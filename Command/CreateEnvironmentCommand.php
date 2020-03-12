<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateEnvironmentCommand extends Command
{
    protected static $defaultName = 'ems:environment:create';

    /** @var LoggerInterface */
    private $logger;

    /** @var EnvironmentService */
    protected $environmentService;

    /** @var DataService */
    protected $dataService;

    /** @var SymfonyStyle */
    private $io;

    const ARGUMENT_ENV_NAME = 'name';
    const OPTION_STRICT = 'strict';

    public function __construct(LoggerInterface $logger, EnvironmentService $environmentService, DataService $dataService)
    {
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        $this->dataService = $dataService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new environment')
            ->addArgument(
                self::ARGUMENT_ENV_NAME,
                InputArgument::REQUIRED,
                'The environment name'
            )
            ->addOption(
                self::OPTION_STRICT,
                null,
                InputOption::VALUE_NONE,
                'If set, the check failed will throw an exception'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create a environment');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->info('Interact with the CreateEnvironment command');

        $this->io->section('Check environment name argument');
        $this->checkEnvironmentNameArgument($input);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Execute the CreateEnvironment command');

        $this->io->section('Execute');
        $environmentName = $input->getArgument(self::ARGUMENT_ENV_NAME);

        $this->io->note(\sprintf('Creation of the environment "%s"...', $environmentName));
        try {
            $environment = $this->environmentService->createEnvironment($environmentName);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return -1;
        }

        try {
            $this->dataService->createAndMapIndex($environment);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            return -1;
        }

        $this->io->success(\sprintf('The environment "%s" was created.', $environmentName));
        return 0;
    }

    private function checkEnvironmentNameArgument(InputInterface $input): void
    {
        $environmentName = $input->getArgument(self::ARGUMENT_ENV_NAME);

        if (null === $environmentName) {
            $message = 'The environment name is not provided';
            $this->setEnvironmentNameArgument($input, $message);
        }

        if (false === $this->environmentService->validateEnvironmentName($environmentName)) {
            $message = 'The new environment name must respects the following regex /^[a-z][a-z0-9\-_]*$/';
            $this->setEnvironmentNameArgument($input, $message);
            $this->checkEnvironmentNameArgument($input);
            return;
        }

        $environment = $this->environmentService->getAliasByName($environmentName);
        if ($environment) {
            $message = \sprintf('The environment "%s" already exist', $environmentName);
            $this->setEnvironmentNameArgument($input, $message);
            $this->checkEnvironmentNameArgument($input);
            return;
        }
    }

    private function setEnvironmentNameArgument(InputInterface $input, string $message): void
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $environmentName = $this->io->ask('Choose an environment name that doesnt exist');
        $input->setArgument(self::ARGUMENT_ENV_NAME, $environmentName);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class EnvironmentCreateCommand extends AbstractCommand
{
    private EnvironmentService $environmentService;
    private DataService $dataService;

    public const ARGUMENT_ENV_NAME = 'name';
    public const OPTION_STRICT = 'strict';
    public const OPTION_UPDATE_REFERRERS = 'update-referrers';

    protected static $defaultName = Commands::ENVIRONMENT_CREATE;

    public function __construct(EnvironmentService $environmentService, DataService $dataService)
    {
        parent::__construct();
        $this->environmentService = $environmentService;
        $this->dataService = $dataService;
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
            ->addOption(
                self::OPTION_UPDATE_REFERRERS,
                null,
                InputOption::VALUE_NONE,
                'If set, update referrers is true'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
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
        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Environment name as to be a string');
        }

        $this->io->note(\sprintf('Creation of the environment "%s"...', $environmentName));
        try {
            $updateReferrers = \boolval($input->getOption(self::OPTION_UPDATE_REFERRERS));
            $environment = $this->environmentService->createEnvironment($environmentName, $updateReferrers);
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
            $environmentName = $this->setEnvironmentNameArgument($input, $message);
        }
        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Unexpected environment name argument');
        }

        if (false === $this->environmentService->validateEnvironmentName($environmentName)) {
            $message = 'The new environment name must respects the following regex /^[a-z][a-z0-9\-_]*$/';
            $this->setEnvironmentNameArgument($input, $message);
            $this->checkEnvironmentNameArgument($input);

            return;
        }

        $environment = $this->environmentService->getAliasByName($environmentName);
        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Environment name as to be a string');
        }
        if ($environment) {
            $message = \sprintf('The environment "%s" already exist', $environmentName);
            $this->setEnvironmentNameArgument($input, $message);
            $this->checkEnvironmentNameArgument($input);
        }
    }

    private function setEnvironmentNameArgument(InputInterface $input, string $message): string
    {
        if ($input->getOption(self::OPTION_STRICT)) {
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $this->io->caution($message);
        $environmentName = $this->io->ask('Choose an environment name that doesnt exist');
        $input->setArgument(self::ARGUMENT_ENV_NAME, $environmentName);

        return $environmentName;
    }
}

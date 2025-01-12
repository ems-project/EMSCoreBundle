<?php

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: Commands::ENVIRONMENT_CREATE,
    description: 'Create a new environment.',
    hidden: false,
    aliases: ['ems:environment:create']
)]
class CreateEnvironmentCommand extends Command
{
    private ?SymfonyStyle $io = null;

    final public const string ARGUMENT_ENV_NAME = 'name';
    final public const string OPTION_STRICT = 'strict';
    final public const string OPTION_UPDATE_REFERRERS = 'update-referrers';

    public function __construct(private readonly LoggerInterface $logger, protected EnvironmentService $environmentService, protected DataService $dataService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
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

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Create a environment');
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (null === $this->io) {
            throw new \RuntimeException('Unexpected null SymfonyStyle');
        }
        $this->logger->info('Interact with the CreateEnvironment command');

        $this->io->section('Check environment name argument');
        $this->checkEnvironmentNameArgument($input);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->io) {
            throw new \RuntimeException('Unexpected null SymfonyStyle');
        }
        $this->logger->info('Execute the CreateEnvironment command');

        $this->io->section('Execute');
        $environmentName = $input->getArgument(self::ARGUMENT_ENV_NAME);
        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Environment name as to be a string');
        }

        $this->io->note(\sprintf('Creation of the environment "%s"...', $environmentName));
        try {
            $updateReferrers = \boolval($input->getOption(self::OPTION_UPDATE_REFERRERS));
            $environment = $this->environmentService->createEnvironment(
                name: $environmentName,
                updateReferrers: $updateReferrers
            );
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

        $this->environmentService->clearCache();

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
        if ($environment) {
            $message = \sprintf('The environment "%s" already exist', $environmentName);
            $this->setEnvironmentNameArgument($input, $message);
            $this->checkEnvironmentNameArgument($input);
        }
    }

    private function setEnvironmentNameArgument(InputInterface $input, string $message): string
    {
        if (null === $this->io) {
            throw new \RuntimeException('Unexpected null SymfonyStyle');
        }
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

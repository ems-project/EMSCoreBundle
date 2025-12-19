<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ENVIRONMENT_CREATE,
    description: 'Create a new environment.',
    aliases: ['ems:environment:create'],
    hidden: false
)]
class CreateEnvironmentCommand extends AbstractCommand
{
    final public const string ARGUMENT_ENV_NAME = 'name';
    final public const string OPTION_STRICT = 'strict';
    final public const string OPTION_UPDATE_REFERRERS = 'update-referrers';
    final public const string OPTION_POSITION = 'position';
    final public const string OPTION_ROLE_PUBLISH = 'role-publish';
    final public const string OPTION_COLOR = 'color';

    public function __construct(
        private readonly LoggerInterface $logger,
        protected EnvironmentService $environmentService,
        protected DataService $dataService
    ) {
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
            ->addOption(
                self::OPTION_POSITION,
                null,
                InputOption::VALUE_REQUIRED,
                'Specifies the position at which the environment should be created'
            )
            ->addOption(
                self::OPTION_COLOR,
                null,
                InputOption::VALUE_REQUIRED,
                'Specifies the color of the environment',
                'default'
            )
            ->addOption(
                self::OPTION_ROLE_PUBLISH,
                null,
                InputOption::VALUE_REQUIRED,
                'Sets the publishing role for the environment (use false to disable publishing)'
            )
        ;
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->info('Interact with the CreateEnvironment command');
        $this->io->section('Check environment name argument');
        $this->checkEnvironmentNameArgument($input);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Environment - Create');
        $this->logger->info('Execute the CreateEnvironment command');

        $this->io->section('Execute');
        $environmentName = $input->getArgument(self::ARGUMENT_ENV_NAME);
        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Environment name as to be a string');
        }

        $this->io->note(\sprintf('Creation of the environment "%s"...', $environmentName));
        try {
            $updateReferrers = (bool) $input->getOption(self::OPTION_UPDATE_REFERRERS);
            $environment = $this->environmentService->createEnvironment(
                name: $environmentName,
                color: $this->getOptionString(self::OPTION_COLOR),
                updateReferrers: $updateReferrers,
                position: $this->getOptionIntNull(self::OPTION_POSITION),
                rolePublish: $this->getRolePublish(),
            );
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            $this->dataService->createAndMapIndex($environment);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());

            return self::FAILURE;
        }

        $this->io->success(\sprintf('The environment "%s" was created.', $environmentName));

        $this->environmentService->clearCache();

        return self::SUCCESS;
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

    private function getRolePublish(): ?string
    {
        $rolePublish = $this->input->getOption(self::OPTION_ROLE_PUBLISH);

        return match (true) {
            'false' === $rolePublish => Roles::NOT_DEFINED,
            \is_string($rolePublish) => $rolePublish,
            default => null,
        };
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

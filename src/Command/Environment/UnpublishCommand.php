<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Environment;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class UnpublishCommand extends AbstractEnvironmentCommand
{
    private Environment $environment;

    public const ARGUMENT_ENVIRONMENT = 'environment';

    protected static $defaultName = Commands::ENVIRONMENT_UNPUBLISH;

    protected function configure(): void
    {
        $this
            ->setDescription('Unpbulish revision from an environment')
            ->addArgument(self::ARGUMENT_ENVIRONMENT, InputArgument::REQUIRED, 'Environment name')
        ;

        $this->configureRevisionSearcher();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Unpublish');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->environment = $this->choiceEnvironment(self::ARGUMENT_ENVIRONMENT, 'Select an existing environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->success($this->environment->getName());

        return self::EXECUTE_SUCCESS;
    }
}

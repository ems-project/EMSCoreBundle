<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ManageAlias;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\ManagedAlias\ManagedAliasManager;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MANAGED_ALIAS_ADD_ENVIRONMENT,
    description: 'Add an environment\'s index to a managed alias.',
    hidden: false
)]
final class AddEnvironmentIndexCommand extends AbstractCommand
{
    public const ARGUMENT_MANAGED_ALIAS_NAME = 'managed-alias-name';
    public const ARGUMENT_ENVIRONMENT_NAME = 'environment-name';
    public const OPTION_CLEAR_ALIAS = 'clear-alias';
    private string $managedAliasName;
    private string $environmentName;
    private bool $clearAlias;

    public function __construct(
        private readonly ManagedAliasManager $managedAliasManager,
        private readonly EnvironmentService $environmentService,
        private readonly IndexService $indexService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_MANAGED_ALIAS_NAME, InputArgument::REQUIRED, 'Managed alias name')
            ->addArgument(self::ARGUMENT_ENVIRONMENT_NAME, InputArgument::REQUIRED, 'Environment name')
            ->addOption(self::OPTION_CLEAR_ALIAS, null, InputOption::VALUE_NONE, 'All existing indexes in the alias will be removed');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - Manage Alias - Add environment index');

        $this->managedAliasName = $this->getArgumentString(self::ARGUMENT_MANAGED_ALIAS_NAME);
        $this->environmentName = $this->getArgumentString(self::ARGUMENT_ENVIRONMENT_NAME);
        $this->clearAlias = $this->getOptionBool(self::OPTION_CLEAR_ALIAS);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = $this->environmentService->getByName($this->environmentName);
        if (!$environment) {
            $this->io->error(\sprintf('Environment %s not found', $this->environmentName));

            return self::FAILURE;
        }
        $managedAlias = $this->managedAliasManager->getByItemName($this->managedAliasName);
        if (null === $managedAlias) {
            $this->io->error(\sprintf('Managed alias %s not found', $this->managedAliasName));

            return self::FAILURE;
        }

        $indexes = $this->indexService->getIndexesByAlias($environment->getAlias());
        $indexesInManagedAlias = $this->indexService->getIndexesByAlias($managedAlias->getAlias());

        $indexesToAdd = \array_diff($indexes, $indexesInManagedAlias);
        $indexesToRemove = [];
        if ($this->clearAlias) {
            $indexesToRemove = \array_diff($indexesInManagedAlias, $indexes);
        }
        if (empty($indexesToAdd) && empty($indexesToRemove)) {
            $this->io->warning('Nothing to add nor to remove');

            return self::EXECUTE_SUCCESS;
        }
        if (!$this->indexService->addIndexesToAlias($managedAlias->getAlias(), $indexesToAdd, $indexesToRemove)) {
            $this->io->error('Something went wrong');
        }

        return self::EXECUTE_SUCCESS;
    }
}

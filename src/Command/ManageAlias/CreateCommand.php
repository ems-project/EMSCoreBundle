<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ManageAlias;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\ManagedAlias\ManagedAliasManager;
use EMS\CoreBundle\Entity\ManagedAlias;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MANAGED_ALIAS_CREATE,
    description: 'Create a managed alias.',
    hidden: false,
    aliases: ['ems:managed-alias:create']
)]
final class CreateCommand extends AbstractCommand
{
    public const string ARGUMENT_NAME = 'name';
    public const string ARGUMENT_LABEL = 'label';
    private string $name;
    private string $label;

    public function __construct(private readonly ManagedAliasManager $managedAliasManager)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_NAME, InputArgument::REQUIRED, 'Alias name')
            ->addArgument(self::ARGUMENT_LABEL, InputArgument::OPTIONAL, 'Alias label');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS Core - Manage Alias - Create');

        $this->name = $this->getArgumentString(self::ARGUMENT_NAME);
        $label = $this->getArgumentStringNull(self::ARGUMENT_LABEL);
        if (null === $label) {
            $label = $this->name;
        }
        $this->label = $label;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $managedAlias = $this->managedAliasManager->getByItemName($this->name);
        if (null === $managedAlias) {
            $managedAlias = new ManagedAlias();
            $managedAlias->setName($this->name);
        } else {
            $this->io->warning('Updating a existing managed alias');
        }
        $managedAlias->setLabel($this->label);
        $this->managedAliasManager->update($managedAlias);
        $this->io->success(\sprintf('Managed alias %s has been created', $this->name));

        return self::EXECUTE_SUCCESS;
    }
}

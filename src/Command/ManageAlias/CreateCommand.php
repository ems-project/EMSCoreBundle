<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ManageAlias;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\ManagedAlias\ManagedAliasManager;
use EMS\CoreBundle\Entity\ManagedAlias;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateCommand extends AbstractCommand
{
    public const ARGUMENT_NAME = 'name';
    public const ARGUMENT_LABEL = 'label';

    protected static $defaultName = Commands::MANAGED_ALIAS_CREATE;
    private string $name;
    private string $label;

    public function __construct(private readonly ManagedAliasManager $managedAliasManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_NAME, InputArgument::REQUIRED, 'Alias name')
            ->addArgument(self::ARGUMENT_LABEL, InputArgument::OPTIONAL, 'Alias label');
    }

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $managedAlias = new ManagedAlias();
        $managedAlias->setName($this->name);
        $managedAlias->setLabel($this->label);
        $this->managedAliasManager->update($managedAlias);
        $this->io->success(\sprintf('Managed alias %s has been created', $this->name));

        return self::EXECUTE_SUCCESS;
    }
}

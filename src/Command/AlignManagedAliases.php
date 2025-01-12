<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\AliasService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::MANAGED_ALIAS_ALIGN,
    description: 'Align a managed alias to another.',
    hidden: false,
    aliases: ['ems:managedalias:align']
)]
class AlignManagedAliases extends Command
{
    public function __construct(protected LoggerInterface $logger, protected AliasService $aliasService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source managed alias name'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target managed alias name'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceName = $input->getArgument('source');
        $targetName = $input->getArgument('target');
        if (!\is_string($targetName)) {
            throw new \RuntimeException('Target name as to be a string');
        }
        if (!\is_string($sourceName)) {
            throw new \RuntimeException('Source name as to be a string');
        }

        $source = $this->aliasService->getManagedAliasByName($sourceName);
        $target = $this->aliasService->getManagedAliasByName($targetName);

        $actions = [
            'add' => [],
            'remove' => [],
        ];
        foreach ($source->getIndexes() as $index) {
            if (!isset($target->getIndexes()[$index['name']])) {
                $actions['add'][] = $index['name'];
            }
        }
        foreach ($target->getIndexes() as $index) {
            if (!isset($source->getIndexes()[$index['name']])) {
                $actions['remove'][] = $index['name'];
            }
        }

        if (empty($actions['add']) && empty($actions['remove'])) {
            $output->writeln(\sprintf('The alias %s was already aligned to the alias %s', $targetName, $sourceName));

            return 0;
        }
        $this->aliasService->updateAlias($target->getAlias(), $actions);
        $output->writeln(\sprintf('The alias %s has been aligned to the alias %s', $targetName, $sourceName));

        return 0;
    }
}

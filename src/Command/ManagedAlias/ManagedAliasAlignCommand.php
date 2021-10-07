<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ManagedAlias;

use EMS\CoreBundle\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\AliasService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ManagedAliasAlignCommand extends AbstractCommand
{
    private AliasService $aliasService;

    protected static $defaultName = Commands::MANAGED_ALIAS_ALIGN;

    public function __construct(AliasService $aliasService)
    {
        parent::__construct();
        $this->aliasService = $aliasService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Align a managed alias to another')
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

        $this->aliasService->build();
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

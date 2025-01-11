<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::ENVIRONMENT_REBUILD,
    description: 'Rebuild an environment in a brand new index.',
    hidden: false,
    aliases: ['ems:environment:rebuild']
)]
class RebuildCommand extends AbstractCommand
{
    final public const string ALL = 'all';
    private bool $signData;
    private int $bulkSize;
    private ObjectManager $em;
    private bool $yellowOk;
    private bool $all;

    public function __construct(private readonly Registry $doctrine, protected LoggerInterface $logger, private readonly ContentTypeService $contentTypeService, private readonly EnvironmentService $environmentService, private readonly ReindexCommand $reindexCommand, private readonly ElasticaService $elasticaService, private readonly Mapping $mapping, private readonly AliasService $aliasService, private readonly string $instanceId, private readonly string $defaultBulkSize)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Environment name'
            )
            ->addOption(
                self::ALL,
                null,
                InputOption::VALUE_NONE,
                'Rebuild all managed indexes'
            )
            ->addOption(
                'yellow-ok',
                null,
                InputOption::VALUE_NONE,
                'Agree to rebuild on a yellow status cluster'
            )
            ->addOption(
                'sign-data',
                null,
                InputOption::VALUE_NONE,
                'Deprecated: the data are signed by default'
            )
            ->addOption(
                'dont-sign',
                null,
                InputOption::VALUE_NONE,
                'Don\'t (re)signed the documents during the rebuilding process'
            )
            ->addOption(
                'bulk-size',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of item that will be indexed together during the same elasticsearch operation',
                $this->defaultBulkSize
            )
        ;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->aliasService->build();
        $this->yellowOk = true === $input->getOption('yellow-ok');
        $this->all = true === $input->getOption(self::ALL);
        $this->waitFor($this->yellowOk, $output);

        $this->bulkSize = \intval($input->getOption('bulk-size'));
        if ($this->bulkSize <= 0) {
            throw new \RuntimeException('Unexpected bulk size option');
        }

        if ($input->getOption('sign-data')) {
            $this->logger->warning('command.rebuild.sign-data');
            $output->writeln('The option --sign-data is deprecated');
        }

        $this->signData = !$input->getOption('dont-sign');

        $this->em = $this->doctrine->getManager();
        $name = $input->getArgument('name');
        $envRepo = $this->em->getRepository(Environment::class);
        if (!$envRepo instanceof EnvironmentRepository) {
            throw new \RuntimeException('Unexpected environment repository');
        }

        if (\is_string($name)) {
            $environment = $envRepo->findOneBy(['name' => $name, 'managed' => true]);
            if (!$environment instanceof Environment) {
                $output->writeln('WARNING: Managed environment named '.$name.' not found');

                return -1;
            }

            $this->rebuildEnvironment($environment, $output);
        } elseif ($this->all) {
            foreach ($envRepo->findAll() as $environment) {
                if (!$environment instanceof Environment) {
                    throw new \RuntimeException('Unexpected environment object');
                }
                if (!$environment->getManaged()) {
                    continue;
                }
                $this->rebuildEnvironment($environment, $output);
            }
        } else {
            throw new \RuntimeException('A content type name argument or the flag --all must be defined');
        }

        return 0;
    }

    private function waitFor(bool $yellowOk, OutputInterface $output): void
    {
        if ($yellowOk) {
            $output->writeln('Waiting for yellow...');
            $this->elasticaService->getClusterHealth('yellow', '30s');
        } else {
            $output->writeln('Waiting for green...');
            $this->elasticaService->getClusterHealth('green', '30s');
        }
    }

    private function rebuildEnvironment(Environment $environment, OutputInterface $output): void
    {
        if ($environment->getAlias() != $this->instanceId.$environment->getName()) {
            $environment->setAlias($this->instanceId.$environment->getName());
            $this->em->persist($environment);
            $this->em->flush();
            $output->writeln('Alias has been aligned to '.$environment->getAlias());
        }

        $contentTypeRepository = $this->em->getRepository(ContentType::class);
        if (!$contentTypeRepository instanceof ContentTypeRepository) {
            throw new \RuntimeException('Unexpected ContentTypeRepository object');
        }
        $contentTypes = $contentTypeRepository->findAll();

        $body = $this->environmentService->getIndexAnalysisConfiguration();

        $newIndexName = $environment->getNewIndexName();
        $this->mapping->createIndex($newIndexName, $body);

        $output->writeln('A new index '.$newIndexName.' has been created');
        $this->waitFor($this->yellowOk, $output);
        $output->writeln(\count($contentTypes).' content types will be re-indexed');

        $countContentType = 1;

        foreach ($contentTypes as $contentType) {
            if (!$contentType instanceof ContentType) {
                throw new \RuntimeException('Unexpected ContentType object');
            }
            if (!$contentType->getDeleted() && $contentType->getEnvironment() && $contentType->giveEnvironment()->getManaged()) {
                $this->contentTypeService->updateMapping($contentType, $newIndexName);
                $output->writeln('A mapping has been defined for '.$contentType->getSingularName());
                ++$countContentType;
            }
        }

        foreach ($contentTypes as $contentType) {
            if (!$contentType instanceof ContentType) {
                throw new \RuntimeException('Unexpected ContentType object');
            }
            if (!$contentType->getDeleted() && $contentType->giveEnvironment()->getManaged()) {
                $this->reindexCommand->reindex($environment->getName(), $contentType, $newIndexName, $output, $this->signData, $this->bulkSize);
                $output->writeln('');
                $output->writeln($contentType->getPluralName().' have been re-indexed ');
            }
        }

        $this->waitFor($this->yellowOk, $output);

        $atomicSwitch = $this->aliasService->atomicSwitch($environment, $newIndexName);

        foreach ($atomicSwitch as $action) {
            if (isset($action['add'])) {
                $output->writeln(\sprintf('The alias <info>%s</info> is now point to : %s', $action['add']['alias'], $action['add']['index']));
            }
        }
    }
}

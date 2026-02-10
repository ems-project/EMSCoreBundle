<?php

declare(strict_types=1);

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
    private const string ARGUMENT_NAME = 'name';
    private const string OPTION_ALL = 'all';
    private const string OPTION_IGNORE_REFERRERS = 'ignore-referrers';
    private const string OPTION_BULK_SIZE = 'bulk-size';
    private const string OPTION_DONT_SIGN = 'dont-sign';
    private const string OPTION_YELLOW_OK = 'yellow-ok';
    private bool $signData;
    private int $bulkSize;
    private ObjectManager $em;
    private bool $yellowOk;
    private bool $all;
    private bool $ignoreReferrers;
    private ?string $environmentName = null;

    public function __construct(private readonly Registry $doctrine, protected LoggerInterface $logger, private readonly ContentTypeService $contentTypeService, private readonly EnvironmentService $environmentService, private readonly ReindexCommand $reindexCommand, private readonly ElasticaService $elasticaService, private readonly Mapping $mapping, private readonly AliasService $aliasService, private readonly string $instanceId, private readonly string $defaultBulkSize)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                self::ARGUMENT_NAME,
                InputArgument::OPTIONAL,
                'Environment name'
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                'Rebuild all managed indexes'
            )
            ->addOption(
                self::OPTION_YELLOW_OK,
                null,
                InputOption::VALUE_NONE,
                'Agree to rebuild on a yellow status cluster'
            )
            ->addOption(
                self::OPTION_DONT_SIGN,
                null,
                InputOption::VALUE_NONE,
                'Don\'t (re)signed the documents during the rebuilding process'
            )
            ->addOption(
                self::OPTION_BULK_SIZE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of item that will be indexed together during the same elasticsearch operation',
                $this->defaultBulkSize
            )
            ->addOption(
                self::OPTION_IGNORE_REFERRERS,
                null,
                InputOption::VALUE_NONE,
                'Don\'t update other aliases that refers to the previous index'
            )
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->environmentName = $this->getArgumentStringNull(self::ARGUMENT_NAME);
        $this->yellowOk = $this->getOptionBool(self::OPTION_YELLOW_OK);
        $this->all = $this->getOptionBool(self::OPTION_ALL);
        $this->bulkSize = $this->getOptionInt(self::OPTION_BULK_SIZE);
        $this->signData = !$this->getOptionBool(self::OPTION_DONT_SIGN);
        $this->ignoreReferrers = $this->getOptionBool(self::OPTION_IGNORE_REFERRERS);

        if ($this->bulkSize <= 0) {
            throw new \RuntimeException('Unexpected bulk size option');
        }
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->aliasService->build();
        $this->waitFor($this->yellowOk, $output);

        $this->em = $this->doctrine->getManager();
        $envRepo = $this->em->getRepository(Environment::class);
        if (!$envRepo instanceof EnvironmentRepository) {
            throw new \RuntimeException('Unexpected environment repository');
        }

        if (null !== $this->environmentName) {
            $environment = $envRepo->findOneBy(['name' => $this->environmentName, 'managed' => true]);
            if (!$environment instanceof Environment) {
                $output->writeln('WARNING: Managed environment named '.$this->environmentName.' not found');

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

        $atomicSwitch = $this->aliasService->atomicSwitch($environment, $newIndexName, $this->ignoreReferrers);

        foreach ($atomicSwitch as $action) {
            if (isset($action['add'])) {
                $output->writeln(\sprintf('The alias <info>%s</info> is now point to : %s', $action['add']['alias'], $action['add']['index']));
            }
        }
    }
}

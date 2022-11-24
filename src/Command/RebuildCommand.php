<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildCommand extends EmsCommand
{
    public const COMMAND = 'ems:environment:rebuild';
    /** @var Registry */
    private $doctrine;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var ReindexCommand */
    private $reindexCommand;
    /** @var string */
    private $instanceId;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var LoggerInterface */
    protected $logger;
    /** @var Mapping */
    private $mapping;
    /** @var AliasService */
    private $aliasService;
    /** @var string */
    private $defaultBulkSize;

    public function __construct(Registry $doctrine, LoggerInterface $logger, ContentTypeService $contentTypeService, EnvironmentService $environmentService, ReindexCommand $reindexCommand, ElasticaService $elasticaService, Mapping $mapping, AliasService $aliasService, string $instanceId, string $defaultBulkSize)
    {
        $this->doctrine = $doctrine;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->reindexCommand = $reindexCommand;
        $this->instanceId = $instanceId;
        $this->elasticaService = $elasticaService;
        $this->logger = $logger;
        $this->mapping = $mapping;
        $this->aliasService = $aliasService;
        $this->defaultBulkSize = $defaultBulkSize;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND)
            ->setDescription('Rebuild an environment in a brand new index')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Environment name'
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->aliasService->build();
        $yellowOk = true === $input->getOption('yellow-ok');
        $this->formatStyles($output);
        $this->waitFor($yellowOk, $output);

        $bulkSize = \intval($input->getOption('bulk-size'));
        if (0 === $bulkSize) {
            throw new \RuntimeException('Unexpected bulk size option');
        }

        if ($input->getOption('sign-data')) {
            $this->logger->warning('command.rebuild.sign-data');
            $output->writeln('The option --sign-data is deprecated');
        }

        $signData = !$input->getOption('dont-sign');

        $em = $this->doctrine->getManager();
        $name = $input->getArgument('name');
        if (!\is_string($name)) {
            throw new \RuntimeException('Unexpected content type name');
        }

        $envRepo = $em->getRepository(Environment::class);
        if (!$envRepo instanceof EnvironmentRepository) {
            throw new \RuntimeException('Unexpected environment repository');
        }

        /** @var Environment|null $environment */
        $environment = $envRepo->findOneBy(['name' => $name, 'managed' => true]);

        if (null === $environment) {
            $output->writeln('WARNING: Environment named '.$name.' not found');

            return -1;
        }

        if ($environment->getAlias() != $this->instanceId.$environment->getName()) {
            $environment->setAlias($this->instanceId.$environment->getName());
            $em->persist($environment);
            $em->flush();
            $output->writeln('Alias has been aligned to '.$environment->getAlias());
        }

        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);
        $contentTypes = $contentTypeRepository->findAll();

        $body = $this->environmentService->getIndexAnalysisConfiguration();

        $newIndexName = $environment->getNewIndexName();
        $this->mapping->createIndex($newIndexName, $body);

        $output->writeln('A new index '.$newIndexName.' has been created');
        $this->waitFor($yellowOk, $output);
        $output->writeln(\count($contentTypes).' content types will be re-indexed');

        $countContentType = 1;

        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $contentTypeEnvironment = $contentType->getEnvironment();
            if (null === $contentTypeEnvironment) {
                throw new \RuntimeException('Unexpected null environment');
            }
            if (!$contentType->getDeleted() && $contentType->getEnvironment() && $contentTypeEnvironment->getManaged()) {
                $this->contentTypeService->updateMapping($contentType, $newIndexName);
                $output->writeln('A mapping has been defined for '.$contentType->getSingularName());
                ++$countContentType;
            }
        }

        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            if (!$contentType->getDeleted() && null !== $contentType->getEnvironment() && $contentType->getEnvironment()->getManaged()) {
                $this->reindexCommand->reindex($name, $contentType, $newIndexName, $output, $signData, $bulkSize);
                $output->writeln('');
                $output->writeln($contentType->getPluralName().' have been re-indexed ');
            }
        }

        $this->waitFor($yellowOk, $output);

        $atomicSwitch = $this->aliasService->atomicSwitch($environment, $newIndexName);

        foreach ($atomicSwitch as $action) {
            if (isset($action['add'])) {
                $output->writeln(\sprintf('The alias <info>%s</info> is now point to : %s', $action['add']['alias'], $action['add']['index']));
            }
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
}

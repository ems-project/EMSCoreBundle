<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Elasticsearch\Client;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\JobRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class RebuildCommand extends EmsCommand
{
    private $mapping;
    private $doctrine;

    /**@var ContentTypeService*/
    private $contentTypeService;
    /**@var EnvironmentService*/
    private $environmentService;
    /**@var ReindexCommand*/
    private $reindexCommand;
    private $instanceId;
    private $singleTypeIndex;

    public function __construct(Registry $doctrine, Logger $logger, Client $client, $mapping, ContentTypeService $contentTypeService, EnvironmentService $environmentService, ReindexCommand $reindexCommand, $instanceId, $singleTypeIndex)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->client = $client;
        $this->mapping = $mapping;
        $this->contentTypeService = $contentTypeService;
        $this->environmentService = $environmentService;
        $this->reindexCommand = $reindexCommand;
        $this->instanceId = $instanceId;
        $this->singleTypeIndex = $singleTypeIndex;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this
            ->setName('ems:environment:rebuild')
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
                1000
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatStyles($output);

        if (! $input->getOption('yellow-ok')) {
            $this->waitForGreen($output);
        }

        if ($input->getOption('sign-data')) {
            $this->logger->warning('command.rebuild.sign-data');
            $output->writeln('The option --sign-data is deprecated');
        }

        $signData = !$input->getOption('dont-sign');

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var  Client $client */
        $client = $this->client;
        $name = $input->getArgument('name');
        /** @var EnvironmentRepository $envRepo */
        $envRepo = $em->getRepository('EMSCoreBundle:Environment');

        /** @var Environment|null $environment */
        $environment = $envRepo->findOneBy(['name' => $name, 'managed' => true]);

        if ($environment === null) {
            $output->writeln("WARNING: Environment named " . $name . " not found");
            return null;
        }

        if ($environment->getAlias() != $this->instanceId . $environment->getName()) {
            $environment->setAlias($this->instanceId . $environment->getName());
            $em->persist($environment);
            $em->flush();
            $output->writeln("Alias has been aligned to " . $environment->getAlias());
        }

        $singleIndexName = $indexName = $environment->getAlias() . AppController::getFormatedTimestamp();
        $indexes = [];


        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $contentTypeRepository->findAll();
        /** @var ContentType $contentType */

        $indexDefaultConfig = $this->environmentService->getIndexAnalysisConfiguration();
        if (!$this->singleTypeIndex) {
            $client->indices()->create([
                'index' => $indexName,
                'body' => $indexDefaultConfig,
            ]);

            $output->writeln('A new index ' . $indexName . ' has been created');
            if (! $input->getOption('yellow-ok')) {
                $this->waitForGreen($output);
            }
        }

        $output->writeln(count($contentTypes) . ' content types will be re-indexed');

        $countContentType = 1;

        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            if (!$contentType->getDeleted() && $contentType->getEnvironment() && $contentType->getEnvironment()->getManaged()) {
                if ($this->singleTypeIndex) {
                    $indexName = $this->environmentService->getNewIndexName($environment, $contentType);
                    $indexes[] = $indexName;
                    $client->indices()->create([
                        'index' => $indexName,
                        'body' => $indexDefaultConfig,
                    ]);

                    $output->writeln('A new index ' . $indexName . ' has been created');
                    if (! $input->getOption('yellow-ok')) {
                        $this->waitForGreen($output);
                    }
                }

                $this->contentTypeService->updateMapping($contentType, $indexName);
                $output->writeln('A mapping has been defined for ' . $contentType->getSingularName());

                if ($this->singleTypeIndex) {
                    $this->reindexCommand->reindex($name, $contentType, $indexName, $output, $signData, $input->getOption('bulk-size'));
                }
                $this->contentTypeService->setSingleTypeIndex($environment, $contentType, $indexName);

                if ($this->singleTypeIndex) {
                    $output->writeln('');
                    $output->writeln($contentType->getPluralName() . ' have been re-indexed ' . $countContentType . '/' . count($contentTypes));
                }
                ++$countContentType;
            }
        }


        if (!$this->singleTypeIndex) {
            /** @var ContentType $contentType */
            foreach ($contentTypes as $contentType) {
                if (!$contentType->getDeleted() && $contentType->getEnvironment() !== null && $contentType->getEnvironment()->getManaged()) {
                    $this->reindexCommand->reindex($name, $contentType, $indexName, $output, $signData, $input->getOption('bulk-size'));
                    $output->writeln('');
                    $output->writeln($contentType->getPluralName() . ' have been re-indexed ');
                }
            }
        }

        if (! $input->getOption('yellow-ok')) {
            $this->waitForGreen($output);
        }
        if (empty($indexes)) {
            $indexes = [$singleIndexName];
        }
        $this->switchAlias($environment->getAlias(), $indexes, $output, true);
        $output->writeln('The alias <info>' . $environment->getName() . '</info> is now pointing to :');
        foreach ($indexes as $index) {
            $output->writeln('     - ' . $index);
        }
    }

    /**
     * Update the alias of an environment to a new index
     *
     * @param string $alias
     * @param array $toIndexes
     * @param OutputInterface $output
     * @param bool $newEnv
     */
    private function switchAlias($alias, $toIndexes, OutputInterface $output, $newEnv = false)
    {
        try {
            $result = $this->client->indices()->getAlias(['name' => $alias]);
            $params ['body']['actions'] = [];

            foreach ($result as $id => $item) {
                $params ['body']['actions'][] = [
                    'remove' => [
                        "index" => $id,
                        "alias" => $alias,
                    ]
                ];
            }

            foreach ($toIndexes as $index) {
                $params ['body']['actions'][] = [
                    'add' => [
                        "index" => $index,
                        "alias" => $alias,
                    ]
                ];
            }

            $this->client->indices()->updateAliases($params);
        } catch (\Throwable $e) {
            if (!$newEnv) {
                $output->writeln('WARNING : Alias ' . $alias . ' not found');
            }
            foreach ($toIndexes as $index) {
                $this->client->indices()->putAlias([
                    'index' => $index,
                    'name' => $alias
                ]);
            }
        }
    }
}

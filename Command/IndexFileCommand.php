<?php

namespace EMS\CoreBundle\Command;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\AssetNotFoundException;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use Exception;
use Monolog\Logger;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class IndexFileCommand extends EmsCommand
{

    /**
     *
     *
     * @var Registry
     */
    protected $doctrine;
    /**
     *
     *
     * @var ContentTypeService
     */
    protected $contentTypeService;
    /**
     *
     *
     * @var AssetExtratorService
     */
    protected $extractorService;
    protected $databaseName;
    protected $databaseDriver;
    /**
     *
     *
     * @var FileService
     */
    protected $fileService;


    public function __construct(Logger $logger, Client $client, Registry $doctrine, ContentTypeService $contentTypeService, AssetExtratorService $extractorService, FileService $fileService)
    {
        $this->doctrine = $doctrine;
        $this->contentTypeService = $contentTypeService;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;
        parent::__construct($logger, $client);
    }

    protected function configure()
    {
        $this
            ->setName('ems:revisions:index-file-fields')
            ->setDescription('Migrate an ingested file field from an elasticsearch index')
            ->addArgument(
                'contentType',
                InputArgument::REQUIRED,
                'Content type name to migrate'
            )
            ->addArgument(
                'field',
                InputArgument::REQUIRED,
                'Field name to migrate'
            )
            ->addOption(
                'only-with-ingested-content',
                null,
                InputOption::VALUE_NONE,
                'Will migrated filed with content subfield only (should be an old ingested asset field)'
            )
            ->addOption(
                'missing-content-only',
                null,
                InputOption::VALUE_NONE,
                'Will migrated filed only without _content'
            );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Please do a backup of your DB first!");
        $helper = $this->getHelper('question');
        $question= new ConfirmationQuestion('Continue?', false);

        if (!$helper->ask($input, $output, $question)) {
            return;
        }


        $contentTypeName = $input->getArgument('contentType');
        $fieldName = $input->getArgument('field');


        $output->write('DB size before the migration : ');
        $this->dbSize($output);

        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType) {
            throw new Exception('Content type not found');
        }

        $onlyWithIngestedContent = $input->getOption('only-with-ingested-content');
        $onlyMissingContent = $input->getOption('missing-content-only');
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $em->getRepository('EMSCoreBundle:Revision');

        $total = $revisionRepository->countByContentType($contentType);

        $offset = 0;
        $limit = 10;


        // create a new progress bar
        $progress = new ProgressBar($output, $total);
        // start and displays the progress bar
        $progress->start();

        while (true) {
            $revisions = $revisionRepository->findBy(['contentType' => $contentType], ['id' => 'asc'], $limit, $offset);

            /** @var Revision $revision */
            foreach ($revisions as $revision) {
                $update = false;
                $rawData = $revision->getRawData();
                if (!empty($rawData) && $this->findField($rawData, $fieldName, $output, $onlyWithIngestedContent, $onlyMissingContent)) {
                    $revision->setRawData($rawData);
                    $update = true;
                }
                unset($rawData);
                $rawData = $revision->getAutoSave();
                if (!empty($rawData) && $this->findField($rawData, $fieldName, $output, $onlyWithIngestedContent, $onlyMissingContent)) {
                    $revision->setAutoSave($rawData);
                    $update = true;
                }
                unset($rawData);

                if ($update) {
                    $revision->setLockBy('SYSTEM_MIGRATE');
                    $date = new DateTime();
                    $date->modify('+5 minutes');
                    $revision->setLockUntil($date);
                    $em->persist($revision);
                    $em->flush($revision);
                }
                unset($revision);

                $progress->advance();
            }

            if (count($revisions) == $limit) {
                unset($revisions);
                $offset += $limit;
            } else {
                break;
            }
        }

        $progress->finish();
        $output->writeln("");
        $output->writeln("Migration done");
        $output->writeln("Please rebuild your environments and update your field type");

        $output->write('DB size after the migration : ');
        $this->dbSize($output);
    }

    /**
     * @param array $rawData
     * @param $field
     * @param OutputInterface $output
     * @param $onlyWithIngestedContent
     * @param $onlyMissingContent
     * @return bool
     * @throws AssetNotFoundException
     */
    private function findField(array &$rawData, $field, OutputInterface $output, $onlyWithIngestedContent, $onlyMissingContent)
    {
        foreach ($rawData as $key => $data) {
            if ($key === $field) {
                if ($onlyMissingContent && isset($rawData[$key]['_content'])) {
                    //do nothing in this case as a content has been already extracted
                } else if ($onlyWithIngestedContent && !isset($rawData[$key]['content'])) {
                    //do nothing in this case as a there is no ingested (binary) content
                } else {
                    return $this->migrate($rawData[$key], $output);
                }
                return false;
            }

            if (is_array($data)) {
                if ($this->findField($rawData[$key], $field, $output, $onlyWithIngestedContent, $onlyMissingContent)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param array $rawData
     * @param OutputInterface $output
     * @return bool
     * @throws AssetNotFoundException
     */
    private function migrate(array &$rawData, OutputInterface $output)
    {
        $updated = false;
        if (!empty($rawData) && !empty($rawData)) {
            if (isset($rawData['sha1'])) {
                $file = $this->fileService->getFile($rawData['sha1']);

                if ((!$file || !file_exists($file)) && isset($rawData['content'])) {
                    $fileContent = base64_decode($rawData['content']);

                    if ($rawData['sha1'] === sha1($fileContent)) {
                        $tempName = $this->fileService->temporaryFilename($rawData['sha1']);
                        file_put_contents($tempName, $fileContent);

                        /**@var StorageInterface $service*/
                        foreach ($this->fileService->getStorages() as $service) {
                            $service->create($rawData['sha1'], $tempName);
                            $output->writeln('File restored from DB: '.$rawData['sha1']);
                            break;
                        }

                        $file = $this->fileService->getFile($rawData['sha1']);
                    }
                }

                if (isset($rawData['content'])) {
                    unset($rawData['content']);
                    $updated = true;
                }

                if ($file) {
                    $data = $this->extractorService->extractData($rawData['sha1'], $file);

                    if (!empty($data)) {
                        if (isset($data['date']) && $data['date']) {
                            $rawData['_date'] = $data['date'];
                            $updated = true;
                        }
                        if (isset($data['content']) && $data['content']) {
                            $rawData['_content'] = $data['content'];
                            $updated = true;
                        }
                        if (isset($data['Author']) && $data['Author']) {
                            $rawData['_author'] = $data['Author'];
                            $updated = true;
                        }
                        if (isset($data['author']) && $data['author']) {
                            $rawData['_author'] = $data['author'];
                            $updated = true;
                        }
                        if (isset($data['language']) && $data['language']) {
                            $rawData['_language'] = $data['language'];
                            $updated = true;
                        }
                        if (isset($data['title']) && $data['title']) {
                            $rawData['_title'] = $data['title'];
                            $updated = true;
                        }
                    }
                } else {
                    $output->writeln('File not found:'.$rawData['sha1']);
                }
            }
        }
        return $updated;
    }

    /**
     * @param OutputInterface $output
     * @throws DBALException
     * @throws Exception
     */
    private function dbSize(OutputInterface $output)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->doctrine->getManager();

        /**@var Connection $connection */
        $connection = $this->doctrine->getConnection();
        $dbName = $connection->getDatabase();

        if (in_array($connection->getDriver()->getName(), ['pdo_pgsql'])) {
            $query = "SELECT pg_size_pretty(pg_database_size('$dbName')) AS size";
        } elseif (in_array($connection->getDriver()->getName(), ['pdo_mysql'])) {
            $query = "SELECT SUM(data_length + index_length)/1024/1024 AS size FROM information_schema.TABLES WHERE table_schema='$dbName' GROUP BY table_schema";
        } else {
            throw new Exception('Not supported driver');
        }
        $stmt = $em->getConnection()->prepare($query);
        $stmt->execute();
        $size = $stmt->fetchAll();


        if (is_array($size) && isset($size[0]['size'])) {
            $row = "The database size is {$size[0]['size']} MB";
        } else {
            $row = 'Undefined';
        }

        $output->writeln($row);
    }
}

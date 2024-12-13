<?php

namespace EMS\CoreBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\FileService;
use EMS\Helpers\File\TempFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: Commands::REVISIONS_INDEX_FILE_FIELDS,
    description: 'Migrate an ingested file field from an elasticsearch index.',
    hidden: false,
    aliases: ['ems:revisions:index-file-fields']
)]
class IndexFileCommand extends AbstractCommand
{
    /** @var string */
    private const SYSTEM_USERNAME = 'SYSTEM_FILE_INDEXER';
    /** @var string */
    protected $databaseName;
    /** @var string */
    protected $databaseDriver;

    public function __construct(protected LoggerInterface $logger, protected Registry $doctrine, protected ContentTypeService $contentTypeService, protected AssetExtractorService $extractorService, protected FileService $fileService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Please do a backup of your DB first!');
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Continue?', false);

        if (!$helper->ask($input, $output, $question)) {
            return -1;
        }

        $contentTypeName = $input->getArgument('contentType');
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $fieldName = $input->getArgument('field');
        if (!\is_string($fieldName)) {
            throw new \RuntimeException('Unexpected field name');
        }

        $output->write('DB size before the migration : ');
        $this->dbSize($output);

        $contentType = $this->contentTypeService->getByName($contentTypeName);
        if (!$contentType) {
            throw new \RuntimeException('Content type not found');
        }

        $onlyWithIngestedContent = true === $input->getOption('only-with-ingested-content');
        $onlyMissingContent = true === $input->getOption('missing-content-only');
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        /** @var RevisionRepository $revisionRepository */
        $revisionRepository = $em->getRepository(Revision::class);

        $total = $revisionRepository->countByContentType($contentType);

        $offset = 0;
        $limit = 10;

        $progress = new ProgressBar($output, $total);
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
                    $revision->setLockBy(self::SYSTEM_USERNAME);
                    $date = new \DateTime();
                    $date->modify('+5 minutes');
                    $revision->setLockUntil($date);
                    $em->persist($revision);
                    $em->flush($revision);
                }
                unset($revision);

                $progress->advance();
            }

            if (\count($revisions) == $limit) {
                unset($revisions);
                $offset += $limit;
            } else {
                break;
            }
        }

        $progress->finish();
        $output->writeln('');
        $output->writeln('Migration done');
        $output->writeln('Please rebuild your environments and update your field type');

        $output->write('DB size after the migration : ');
        $this->dbSize($output);

        return 0;
    }

    /**
     * @param array<mixed> $rawData
     */
    private function findField(array &$rawData, string $field, OutputInterface $output, bool $onlyWithIngestedContent, bool $onlyMissingContent): bool
    {
        foreach ($rawData as $key => $data) {
            if ($key === $field) {
                if ($onlyMissingContent && isset($rawData[$key]['_content'])) {
                    // do nothing in this case as a content has been already extracted
                } elseif ($onlyWithIngestedContent && !isset($rawData[$key]['content'])) {
                    // do nothing in this case as a there is no ingested (binary) content
                } else {
                    return $this->migrate($rawData[$key], $output);
                }

                return false;
            }

            if (\is_array($data)) {
                if ($this->findField($rawData[$key], $field, $output, $onlyWithIngestedContent, $onlyMissingContent)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $rawData
     */
    private function migrate(array &$rawData, OutputInterface $output): bool
    {
        $updated = false;
        if (!empty($rawData)) {
            if (isset($rawData['sha1'])) {
                $file = $this->fileService->getFile($rawData['sha1']);

                if (null === $file && isset($rawData['content'])) {
                    $fileContent = \base64_decode((string) $rawData['content']);

                    if (\sha1($fileContent) === $rawData[EmsFields::CONTENT_FILE_HASH_FIELD]) {
                        $tempFile = TempFile::create();
                        $file = $tempFile->path;
                        \file_put_contents($file, $fileContent);
                        try {
                            $this->fileService->uploadFile($rawData[EmsFields::CONTENT_FILE_NAME_FIELD] ?? 'filename.bin', $rawData[EmsFields::CONTENT_MIME_TYPE_FIELD] ?? 'application/bin', $file, self::SYSTEM_USERNAME);
                            $output->writeln(\sprintf('File restored from DB: %s', $rawData[EmsFields::CONTENT_FILE_HASH_FIELD]));
                        } catch (\Throwable) {
                            $file = null;
                        }
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

    private function dbSize(OutputInterface $output): void
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->doctrine->getManager();

        $connection = $this->doctrine->getConnection();
        if (!$connection instanceof Connection) {
            throw new \RuntimeException('Unexpected doctrine connection');
        }
        $dbName = $connection->getDatabase();

        if (\in_array($connection->getDriver()->getDatabasePlatform()->getName(), ['postgresql'])) {
            $query = "SELECT pg_size_pretty(pg_database_size('$dbName')) AS size";
        } elseif (\in_array($connection->getDriver()->getDatabasePlatform()->getName(), ['mysql'])) {
            $query = "SELECT SUM(data_length + index_length)/1024/1024 AS size FROM information_schema.TABLES WHERE table_schema='$dbName' GROUP BY table_schema";
        } else {
            throw new \RuntimeException('Not supported driver');
        }
        $stmt = $em->getConnection()->prepare($query);
        $result = $stmt->executeQuery();
        $size = $result->fetchAllAssociative();

        if (\is_array($size) && isset($size[0]['size'])) {
            $row = "The database size is {$size[0]['size']} MB";
        } else {
            $row = 'Undefined';
        }

        $output->writeln($row);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Environment;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

final class ReindexCommand extends AbstractCommand
{
    private EnvironmentService $environmentService;
    private ContentTypeService $contentTypeService;
    private RevisionRepository $revisionRepository;
    private DataService $dataService;
    private Bulker $bulker;
    private int $defaultBulkSize;
    /** @var array<mixed> */
    private array $summary = [];

    private ?string $index = null;
    private bool $reloadData;

    public const ARGUMENT_ENVIRONMENT = 'environment';
    public const ARGUMENT_CONTENT_TYPE = 'content-type';
    public const ARGUMENT_INDEX = 'index';
    public const OPTION_SIGN_DATA = 'sign-data';
    public const OPTION_RELOAD_DATA = 'reload-data';
    public const OPTION_BULK_SIZE = 'bulk-size';

    protected static $defaultName = Commands::ENVIRONMENT_REINDEX;

    public function __construct(
        EnvironmentService $environmentService,
        ContentTypeService $contentTypeService,
        RevisionRepository $revisionRepository,
        DataService $dataService,
        Bulker $bulker,
        string $defaultBulkSize)
    {
        $this->environmentService = $environmentService;
        $this->contentTypeService = $contentTypeService;
        $this->revisionRepository = $revisionRepository;
        $this->dataService = $dataService;
        $this->bulker = $bulker;
        $this->defaultBulkSize = \intval($defaultBulkSize);

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Reindex environment documents')
            ->addArgument(self::ARGUMENT_ENVIRONMENT, InputArgument::REQUIRED, 'Environment name')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::OPTIONAL, 'If not defined all content types will be re-indexed')
            ->addArgument(self::ARGUMENT_INDEX, InputArgument::OPTIONAL, 'Elasticsearch index where to index environment objects')
            ->addOption(self::OPTION_SIGN_DATA, null, InputOption::VALUE_NONE, 'The content won\'t be (re)signed during the reindexing process')
            ->addOption(self::OPTION_RELOAD_DATA, null, InputOption::VALUE_NONE, 'Reload the data')
            ->addOption(self::OPTION_BULK_SIZE, null, InputOption::VALUE_OPTIONAL, 'Number of item that will be indexed together during the same elasticsearch operation', $this->defaultBulkSize);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $managedEnvironments = $this->environmentService->getManagedEnvironement();
        $environmentNames = \array_map(fn (Environment $environment) => $environment->getName(), $managedEnvironments);
        $this->choiceArgumentString(self::ARGUMENT_ENVIRONMENT, 'Select an environment', \array_values($environmentNames));
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io->title('EMS - Environment - Reindex');

        $this->index = $this->getArgumentStringNull(self::ARGUMENT_INDEX);
        $this->reloadData = $this->getOptionBool(self::OPTION_RELOAD_DATA);

        $this->bulker->setSign($this->getOptionBool(self::OPTION_SIGN_DATA));
        $this->bulker->setSize($this->getOptionInt(self::OPTION_BULK_SIZE, $this->defaultBulkSize));
        $this->bulker->setLogger(new ConsoleLogger($output));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $environment = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_ENVIRONMENT));
            $this->index = $this->index ?? $environment->getAlias();

            if ($contentTypeName = $this->getArgumentStringNull(self::ARGUMENT_CONTENT_TYPE)) {
                $contentTypes = [$this->contentTypeService->giveByName($contentTypeName)];
            } else {
                $contentTypes = $this->contentTypeService->getAll();
            }
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());

            return self::EXECUTE_ERROR;
        }

        foreach ($contentTypes as $contentType) {
            $countDeleted = $countIndexed = 0;
            $this->io->section(\sprintf('Start reindex: %s', $contentType->getName()));

            $paginator = $this->revisionRepository->paginateByEnvironmentContentType($environment, $contentType, $this->bulker->getSize());
            $paginator->disableLogging();

            $progressBar = $this->io->createProgressBar($paginator->count());

            foreach ($paginator as $revision) {
                if ($revision->getDeleted()) {
                    $this->bulker->delete($contentType->getName(), $this->index, $revision->getOuuid());
                    ++$countDeleted;
                } else {
                    if ($this->reloadData) {
                        $this->dataService->reloadData($revision);
                    }

                    $rawData = $revision->getRawData();
                    $this->bulker->index($contentType->getName(), $revision->getOuuid(), $this->index, $rawData);
                    ++$countIndexed;
                }

                $progressBar->advance();
            }

            $this->bulker->send(true);
            $progressBar->finish();
            $this->io->newLine(2);

            $this->summary[] = [$contentType->getName(), $countIndexed, $countDeleted];
        }

        $this->io->section('Summary');
        $this->io->table(['Content type', 'Indexed documents', 'Deleted revisions'], $this->summary);

        return self::EXECUTE_SUCCESS;
    }
}

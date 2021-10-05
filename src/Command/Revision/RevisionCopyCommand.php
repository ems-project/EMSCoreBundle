<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use Elastica\Scroll;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\Revision\Copy\CopyContext;
use EMS\CoreBundle\Service\Revision\Copy\CopyContextFactory;
use EMS\CoreBundle\Service\Revision\Copy\CopyService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RevisionCopyCommand extends Command implements CommandInterface
{
    /** @var CopyContextFactory */
    private $copyContextFactory;
    /** @var CopyService */
    private $copyService;
    /** @var ElasticsearchService */
    private $elasticsearchService;
    /** @var ElasticaService */
    private $elasticaService;
    /** @var SymfonyStyle */
    private $io;
    /** @var Revision[] */
    private $copies = [];

    protected static $defaultName = Commands::REVISION_COPY;

    private const ARG_ENVIRONMENT_NAME = 'environment';
    private const ARG_JSON_SEARCH_QUERY = 'json_search_query';
    private const ARG_JSON_MERGE = 'json_merge';
    private const OPTION_BULK_SIZE = 'bulk-size';

    public function __construct(
        CopyContextFactory $copyRequestFactory,
        CopyService $copyService,
        ElasticsearchService $elasticsearchService,
        ElasticaService $elasticaService
    ) {
        parent::__construct();
        $this->copyContextFactory = $copyRequestFactory;
        $this->copyService = $copyService;
        $this->elasticsearchService = $elasticsearchService;
        $this->elasticaService = $elasticaService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Copy revisions from search query')
            ->addArgument(
                self::ARG_ENVIRONMENT_NAME,
                InputArgument::REQUIRED,
                'environment name'
            )
            ->addArgument(
                self::ARG_JSON_SEARCH_QUERY,
                InputArgument::REQUIRED,
                'JSON search query (escaped)'
            )
            ->addArgument(
                self::ARG_JSON_MERGE,
                InputArgument::OPTIONAL,
                'JSON merge for copied revisions'
            )
            ->addOption(
                self::OPTION_BULK_SIZE,
                null,
                InputOption::VALUE_REQUIRED,
                'Bulk size',
                '25'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Copy revisions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environmentName = $input->getArgument(self::ARG_ENVIRONMENT_NAME);
        $searchQuery = $input->getArgument(self::ARG_JSON_SEARCH_QUERY);
        $jsonMerge = $input->getArgument(self::ARG_JSON_MERGE) ?? '';

        if (!\is_string($environmentName)) {
            throw new \RuntimeException('Unexpected environment name');
        }
        if (!\is_string($searchQuery)) {
            throw new \RuntimeException('Unexpected search query');
        }
        if (!\is_string($jsonMerge)) {
            throw new \RuntimeException('Unexpected JSON merge');
        }

        $copyContext = $this->copyContextFactory->fromJSON(
            $environmentName,
            $searchQuery,
            $jsonMerge
        );

        $search = $copyContext->getSearch();
        $size = \intval($input->getOption(self::OPTION_BULK_SIZE));
        if (0 === $size) {
            throw new \RuntimeException('Unexpected bulk size argument');
        }
        $search->setSize($size);
        $scroll = $this->elasticaService->scroll($search);
        $total = $this->elasticaService->count($search);
        $this->io->note(\sprintf('Found %d documents', $total));
        $this->copy($copyContext, $scroll, $total);

        $countCopies = \count($this->copies);
        $this->io->newLine();
        $this->io->success(\sprintf('Created %d copies', $countCopies));

        return $countCopies;
    }

    private function copy(CopyContext $copyContext, Scroll $scroll, int $total): void
    {
        $progressBar = $this->io->createProgressBar($total);

        foreach ($scroll as $resultSet) {
            foreach ($resultSet as $result) {
                if (false === $result) {
                    continue;
                }
                $this->copies[] = $this->copyService->copyFromResult($copyContext, $result);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->io->newLine();
    }
}

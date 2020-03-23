<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Elasticsearch\Document\DocumentCollectionInterface;
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
    /** @var SymfonyStyle */
    private $io;
    /** @var Revision[] */
    private $copies = [];

    protected static $defaultName = 'ems:revision:copy';

    private const ARG_ENVIRONMENT_NAME = 'environment';
    private const ARG_JSON_SEARCH_QUERY = 'json_search_query';
    private const OPTION_BULK_SIZE = 'bulk-size';

    public function __construct(
        CopyContextFactory $copyRequestFactory,
        CopyService $copyService,
        ElasticsearchService $elasticsearchService
    ) {
        parent::__construct();
        $this->copyContextFactory = $copyRequestFactory;
        $this->copyService = $copyService;
        $this->elasticsearchService = $elasticsearchService;
    }

    protected function configure()
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
            ->addOption(
                self::OPTION_BULK_SIZE,
                null,
                InputOption::VALUE_REQUIRED,
                'Bulk size',
                25
            );
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Copy revisions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $copyContext = $this->copyContextFactory->fromJSON(
            $input->getArgument(self::ARG_ENVIRONMENT_NAME),
            $input->getArgument(self::ARG_JSON_SEARCH_QUERY)
        );

        $request = $copyContext->makeRequest();
        $request->setSize((int) $input->getOption(self::OPTION_BULK_SIZE));

        foreach ($this->elasticsearchService->scroll($request) as $i => $response) {
            if (0 === $i) {
                $this->io->note(sprintf('Found %s documents', $response->getTotal()));
            }

            $this->copy($copyContext, $response->getDocumentCollection());
        }

        $countCopies = \count($this->copies);
        $this->io->newLine();
        $this->io->success(sprintf('Created %d copies', $countCopies));

        return $countCopies;
    }

    private function copy(CopyContext $copyContext, DocumentCollectionInterface $documents): void
    {
        $progressBar = $this->io->createProgressBar($documents->count());

        foreach ($this->copyService->copyFromDocuments($documents) as $copiedRevision) {
            $this->copies[] = $copiedRevision;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->io->newLine();
    }
}

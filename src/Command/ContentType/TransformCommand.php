<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ContentType;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformer;
use EMS\CoreBundle\Core\ContentType\Transformer\ContentTransformerInterface;
use EMS\CoreBundle\Core\Revision\Search\RevisionSearcher;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TransformCommand extends AbstractCommand
{
    private RevisionSearcher $revisionSearcher;
    private ContentTypeService $contentTypeService;
    private ContentTransformer $contentTransformer;

    private ContentType $contentType;
    private string $searchQuery;

    public const ARGUMENT_CONTENT_TYPE = 'content-type';
    public const OPTION_SCROLL_SIZE = 'scroll-size';
    public const OPTION_SCROLL_TIMEOUT = 'scroll-timeout';
    public const OPTION_SEARCH_QUERY = 'search-query';
    public const OPTION_DRY_RUN = 'dry-run';

    protected static $defaultName = Commands::CONTENT_TYPE_TRANSFORM;

    public function __construct(
        RevisionSearcher $revisionSearcher,
        ContentTypeService $contentTypeService,
        ContentTransformer $contentTransformer
    ) {
        parent::__construct();
        $this->revisionSearcher = $revisionSearcher;
        $this->contentTypeService = $contentTypeService;
        $this->contentTransformer = $contentTransformer;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType name')
            ->addOption(self::OPTION_SCROLL_SIZE, null, InputOption::VALUE_REQUIRED, 'Size of the elasticsearch scroll request')
            ->addOption(self::OPTION_SCROLL_TIMEOUT, null, InputOption::VALUE_REQUIRED, 'Time to migrate "scrollSize" items i.e. 30s or 2m')
            ->addOption(self::OPTION_SEARCH_QUERY, null, InputOption::VALUE_OPTIONAL, 'Query used to find elasticsearch records to transform', '{}')
            ->addOption(self::OPTION_DRY_RUN, '', InputOption::VALUE_NONE, 'dry run')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io->title('EMS - Content Type - Transform');

        if ($scrollSize = $this->getOptionIntNull(self::OPTION_SCROLL_SIZE)) {
            $this->revisionSearcher->setSize($scrollSize);
        }
        if ($scrollTimeout = $this->getOptionStringNull(self::OPTION_SCROLL_TIMEOUT)) {
            $this->revisionSearcher->setTimeout($scrollTimeout);
        }

        $this->searchQuery = $this->getOptionString(self::OPTION_SEARCH_QUERY);
        $this->contentType = $this->contentTypeService->giveByName($this->getArgumentString(self::ARGUMENT_CONTENT_TYPE));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transformerDefinitions = $this->contentTransformer->getTransformerDefinitions($this->contentType);
        if (0 === \count($transformerDefinitions)) {
            $this->io->warning('No transformers defined!');

            return self::EXECUTE_SUCCESS;
        }

        if (false === $this->validateTransformerDefinitions($transformerDefinitions)) {
            $this->io->error('Transformers are not valid defined!');

            return self::EXECUTE_ERROR;
        }

        if ($dryRun = $this->getOptionBool(self::OPTION_DRY_RUN)) {
            $this->io->note('Dry run enabled, no database changes');
        }

        $environment = $this->contentType->giveEnvironment();
        $search = $this->revisionSearcher->create($environment, $this->searchQuery, [$this->contentType->getName()]);
        $this->io->progressStart($search->getTotal());

        $transformed = 0;

        foreach ($this->revisionSearcher->search($environment, $search) as $revisions) {
            $this->revisionSearcher->lock($revisions, ContentTransformer::USER);

            foreach ($revisions->transaction() as $revision) {
                $result = $this->contentTransformer->transform($revision, $transformerDefinitions, $dryRun);
                if ($result) {
                    ++$transformed;
                }

                $this->io->progressAdvance();
            }

            $this->revisionSearcher->unlock($revisions);
        }
        $this->io->progressFinish();

        if ($dryRun) {
            $this->io->warning(\sprintf('%d revisions', $transformed));
        } else {
            $this->io->success(\sprintf('Transformed %d revisions', $transformed));
        }

        return self::EXECUTE_SUCCESS;
    }

    /**
     * @param array<mixed> $transformerDefinitions
     */
    private function validateTransformerDefinitions(array $transformerDefinitions): bool
    {
        $this->io->section('Validate transformers');
        $valid = true;

        foreach ($transformerDefinitions as $field => $definitions) {
            foreach ($definitions as $definition) {
                /** @var ContentTransformerInterface $transformer */
                $transformer = $definition['transformer'];

                $this->io->definitionList(
                    ['Field' => $field],
                    ['Name' => $transformer->getName()],
                    ['Config' => $definition['config']],
                    ['Valid Config' => $definition['valid_config'] ?: 'Yes'],
                );

                if ($definition['valid_config']) {
                    $valid = false;
                }
            }
        }

        return $valid;
    }
}

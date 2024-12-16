<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Revision;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::REVISION_DELETE,
    description: 'Delete all/oldest revisions for content type(s).',
    hidden: false
)]
class DeleteCommand extends AbstractCommand
{
    private const ARGUMENT_CONTENT_TYPES = 'content-types';
    private const OPTION_MODE = 'mode';
    private const OPTION_QUERY = 'query';
    private const OPTION_OUUID = 'ouuid';

    private const MODE_ALL = 'all';
    private const MODE_BY_QUERY = 'by-query';
    private const MODE_OLDEST = 'oldest';

    private const MODES = [self::MODE_ALL, self::MODE_OLDEST, self::MODE_BY_QUERY];

    /** @var string[] */
    private array $contentTypeNames = [];
    private string $mode;

    public function __construct(
        private readonly RevisionService $revisionService,
        private readonly ContentTypeService $contentTypeService,
        private readonly PublishService $publishService,
        private readonly ElasticaService $elasticaService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPES, InputArgument::IS_ARRAY, 'contentType names or "all"')
            ->addOption(self::OPTION_MODE, null, InputOption::VALUE_REQUIRED, 'mode for deletion [all,oldest,by-query]', 'all')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'query to use in by-query mode')
            ->addOption(self::OPTION_OUUID, null, InputOption::VALUE_OPTIONAL, 'OUUID of the document to delete')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->mode = $this->getOptionString(self::OPTION_MODE);
        if (\in_array($this->mode, [self::MODE_ALL, self::MODE_OLDEST])) {
            $this->choiceArgumentArray(
                self::ARGUMENT_CONTENT_TYPES,
                'Select one or more contentType(s)',
                $this->contentTypeService->getAllNames()
            );
        }

        $this->contentTypeNames = $this->getArgumentOptionalStringArray(self::ARGUMENT_CONTENT_TYPES);

        if (!\in_array($this->mode, self::MODES)) {
            throw new \RuntimeException(\sprintf('Invalid option "%s"', $this->mode));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('EMSCO - Revision - Delete');

        $queryJson = $this->getOptionStringNull(self::OPTION_QUERY);
        $ouuid = $this->getOptionStringNull(self::OPTION_OUUID);
        if (self::MODE_BY_QUERY === $this->mode) {
            if (null !== $ouuid) {
                throw new \RuntimeException(\sprintf('The %s option is forbidden in %s mode', self::OPTION_OUUID, $this->mode));
            }
            if (null === $queryJson) {
                throw new \RuntimeException(\sprintf('The %s option is required in %s mode', self::OPTION_QUERY, $this->mode));
            }

            return $this->deleteByQuery($queryJson);
        }
        if (null !== $queryJson) {
            throw new \RuntimeException(\sprintf('The %s option is forbidden in %s mode', self::OPTION_QUERY, $this->mode));
        }

        $this->io->note(\sprintf('Selected "%s" contentType(s)', \implode(',', $this->contentTypeNames)));
        $results = [];
        foreach ($this->contentTypeNames as $contentTypeName) {
            $contentType = $this->contentTypeService->giveByName($contentTypeName);
            $this->io->section(\sprintf('Content Type: %s', $contentTypeName));

            if (self::MODE_ALL === $this->mode) {
                if (null !== $ouuid) {
                    throw new \RuntimeException(\sprintf('The %s option is forbidden in %s mode', self::OPTION_OUUID, $this->mode));
                }
                $this->publishService->unpublishByContentType($contentType);
                $result = $this->revisionService->deleteByContentType($contentType);
                $results[] = \sprintf('Deleted all %d "%s" revisions', $result, $contentTypeName);
            } elseif (self::MODE_OLDEST === $this->mode) {
                $result = $this->revisionService->deleteOldest($contentType, $ouuid);
                $results[] = \sprintf('Deleted oldest %d "%s" revisions', $result, $contentTypeName);
            }
        }

        if ($results) {
            $this->io->success($results);
        }

        return parent::EXECUTE_SUCCESS;
    }

    private function deleteByQuery(string $queryJson): int
    {
        $search = $this->elasticaService->convertElasticsearchSearch(Json::decode($queryJson));
        $this->io->progressStart($this->elasticaService->count($search));
        $scroll = $this->elasticaService->scroll($search);
        $counter = 0;
        foreach ($scroll as $resultSet) {
            $ouuids = [];
            foreach ($resultSet as $result) {
                $ouuid = $result->getDocument()->getId();
                if (null === $ouuid) {
                    throw new \RuntimeException('Unexpected null ouuid');
                }
                $ouuids[] = $ouuid;
            }
            $this->publishService->unpublishByOuuids($ouuids);
            $counter += $this->revisionService->deleteByOuuids($ouuids);
            $this->io->progressAdvance(\count($ouuids));
        }
        $this->io->progressFinish();
        $this->io->success(\sprintf('Deleted %d documents', $counter));

        return parent::EXECUTE_SUCCESS;
    }
}

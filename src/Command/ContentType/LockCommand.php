<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\ContentType;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: Commands::CONTENT_TYPE_LOCK,
    description: 'Lock a content type.',
    hidden: false,
    aliases: ['ems:contenttype:lock']
)]
final class LockCommand extends Command
{
    private string $by;
    private ContentType $contentType;
    private bool $force;
    private SymfonyStyle $io;
    private string $query;
    private \DateTime $until;

    public const string ARGUMENT_CONTENT_TYPE = 'contentType';
    public const string ARGUMENT_TIME = 'time';
    public const string OPTION_QUERY = 'query';
    public const string OPTION_USER = 'user';
    public const string OPTION_FORCE = 'force';
    public const string OPTION_IF_EMPTY = 'if-empty';
    public const string OPTION_OUUID = 'ouuid';

    public const int RESULT_SUCCESS = 0;

    public function __construct(
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly ElasticaService $elasticaService,
        private readonly RevisionRepository $revisionRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'content type to recompute')
            ->addArgument(self::ARGUMENT_TIME, InputArgument::REQUIRED, 'lock until (+1day, +5min, now)')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'ES query', '{}')
            ->addOption(self::OPTION_USER, null, InputOption::VALUE_REQUIRED, 'lock username', 'EMS_COMMAND')
            ->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'do not check for already locked revisions')
            ->addOption(self::OPTION_IF_EMPTY, null, InputOption::VALUE_NONE, 'lock if there are no pending locks for the same user')
            ->addOption(self::OPTION_OUUID, null, InputOption::VALUE_OPTIONAL, 'lock a specific ouuid')
        ;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Content-type lock command');

        $timeArgument = $input->getArgument(self::ARGUMENT_TIME);
        if (!\is_string($timeArgument)) {
            throw new \RuntimeException('Unexpected time argument');
        }
        if (($time = \strtotime($timeArgument)) === false) {
            throw new \RuntimeException('invalid time');
        }
        $until = new \DateTime();
        $until->setTimestamp($time);
        $this->until = $until;

        $contentTypeName = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!\is_string($contentTypeName)) {
            throw new \RuntimeException('Unexpected content type name');
        }
        $contentType = $this->contentTypeRepository->findByName($contentTypeName);
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Content type not found');
        }
        $this->contentType = $contentType;

        $by = $input->getOption(self::OPTION_USER);
        if (!\is_string($by)) {
            throw new \RuntimeException('Unexpected username');
        }
        $this->by = $by;

        if (null !== $input->getOption(self::OPTION_QUERY)) {
            $this->query = (string) $input->getOption('query');
            Json::decode($this->query, 'Invalid json query');
        }

        $this->force = true === $input->getOption(self::OPTION_FORCE);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption(self::OPTION_IF_EMPTY)
            && 0 !== $this->revisionRepository->findAllLockedRevisions($this->contentType, $this->by)->count()) {
            return 0;
        }

        $query = Json::decode($this->query);
        if (!empty($query)) {
            $search = $this->elasticaService->convertElasticsearchSearch([
                'index' => (null !== $this->contentType->getEnvironment()) ? $this->contentType->getEnvironment()->getAlias() : '',
                '_source' => false,
                'body' => $query,
            ]);

            $documentCount = $this->elasticaService->count($search);
            if (0 === $documentCount) {
                $this->io->error(\sprintf('No document found in %s with this query : %s', $this->contentType->getName(), $this->query));

                return -1;
            }
            $this->io->comment(\sprintf('%s document(s) found in %s with this query : %s', $documentCount, $this->contentType->getName(), $this->query));

            $revisionCount = 0;
            foreach ($this->searchDocuments($search) as $document) {
                $revisionCount += $this->revisionRepository->lockRevisions($this->contentType, $this->until, $this->by, $this->force, $document->getId());
            }
        } else {
            $ouuid = $input->getOption(self::OPTION_OUUID) ? (string) ($input->getOption(self::OPTION_OUUID)) : null;
            $revisionCount = $this->revisionRepository->lockRevisions($this->contentType, $this->until, $this->by, $this->force, $ouuid);
        }

        if (0 === $revisionCount) {
            $this->io->error('No revisions locked, try force?');

            return -1;
        }

        $this->io->success(\vsprintf('%s locked %d %s revisions until %s by %s', [
            $this->force ? 'FORCE ' : '',
            $revisionCount,
            $this->contentType->getName(),
            $this->until->format('Y-m-d H:i:s'),
            $this->by,
        ]));

        return self::RESULT_SUCCESS;
    }

    /**
     * @return \Generator|DocumentInterface[]
     */
    private function searchDocuments(Search $search): \Generator
    {
        foreach ($this->elasticaService->scroll($search) as $resultSet) {
            foreach ($resultSet as $result) {
                yield Document::fromResult($result);
            }
        }
    }
}

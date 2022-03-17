<?php

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Entity\ReleaseRevision;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\ReleaseService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateReleaseCommand extends Command
{
    protected static $defaultName = Commands::RELEASE_CREATE;
    /** @var LoggerInterface */
    protected $logger;
    /** @var ReleaseService */
    protected $releaseService;
    /** @var EnvironmentService */
    protected $environmentService;
    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var RevisionService */
    protected $revisionService;
    /** @var ElasticaService */
    protected $elasticaService;

    private SymfonyStyle $io;
    private ContentType $contentType;
    private Environment $target;
    private string $query;

    private const ARGUMENT_CONTENT_TYPE = 'contentType';
    private const ARGUMENT_TARGET = 'target';
    private const OPTION_QUERY = 'query';

    public function __construct(LoggerInterface $logger, ReleaseService $releaseService, EnvironmentService $environmentService, ContentTypeService $contentTypeService, RevisionService $revisionService, ElasticaService $elasticaService)
    {
        $this->logger = $logger;
        $this->releaseService = $releaseService;
        $this->environmentService = $environmentService;
        $this->contentTypeService = $contentTypeService;
        $this->revisionService = $revisionService;
        $this->elasticaService = $elasticaService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add documents for a given contenttype in a release')
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Target managed alias name')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'ES query', '{}');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $targetName = $input->getArgument(self::ARGUMENT_TARGET);
        if (!\is_string($targetName)) {
            throw new \RuntimeException('Target name has to be a string');
        }
        $target = $this->environmentService->getByName($targetName);
        if (!$target) {
            throw new \RuntimeException('Unexpected null target environment');
        }
        $this->target = $target;

        $contentType = $input->getArgument(self::ARGUMENT_CONTENT_TYPE);
        if (!\is_string($contentType)) {
            throw new \RuntimeException('ContentType has to be a string');
        }
        $contentType = $this->contentTypeService->getByName($contentType);
        if (!$contentType) {
            throw new \RuntimeException('Unexpected null contenttype');
        }
        $this->contentType = $contentType;
        if (null !== $input->getOption(self::OPTION_QUERY)) {
            $this->query = $input->getOption(self::OPTION_QUERY);
            if (!\is_string($this->query)) {
                throw new \RuntimeException('Unexpected query argument');
            }
            $body = \json_decode($this->query, true);
            if (\json_last_error() > 0) {
                throw new \RuntimeException(\sprintf('Invalid json query %s', $this->query));
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->contentType->getEnvironment()) {
            throw new \RuntimeException('Unexpected null contenttype');
        }
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title(\sprintf('EMSCO - Add Release for %s', $this->contentType));

        $query = \json_decode($this->query, true);
        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $this->contentType->getEnvironment()->getAlias(),
            '_source' => false,
            'type' => $this->contentType->getName(),
            'body' => \count($query) > 0 ? $query : null,
        ]);

        $documentCount = $this->elasticaService->count($search);
        if (0 === $documentCount) {
            $this->io->error(\count($query) > 0 ? \sprintf('No document found in %s with this query : %s', $this->contentType->getName(), $this->query) : \sprintf('No document found in %s', $this->contentType->getName()));

            return -1;
        }

        $this->io->comment(\count($query) > 0 ? \sprintf('%s document(s) found in %s with this query : %s', $documentCount, $this->contentType->getName(), $this->query) : \sprintf('%s document(s) found in %s', $documentCount, $this->contentType->getName()));

        $release = new Release();
        $release->setName(\sprintf('CMD-Release for %s to %s', $this->contentType, $this->target->getName()));

        $release->setEnvironmentSource($this->contentType->getEnvironment());
        $release->setEnvironmentTarget($this->target);

        $revisionCount = 0;
        foreach ($this->searchDocuments($search) as $document) {
            $revision = $this->revisionService->getByEmsLink(EMSLink::fromText($document->getEmsId()));
            if (null !== $revision && !$revision->isPublished($this->target->getName())) {
                $releaseRevision = new ReleaseRevision();
                $releaseRevision->setRelease($release);
                $releaseRevision->setRevisionOuuid($document->getId());
                $releaseRevision->setContentType($this->contentType);
                $releaseRevision->setRevision($revision);
                $release->addRevision($releaseRevision);
            }
        }

        $this->releaseService->add($release);
        $this->io->success(\sprintf('Release %s has created', $release->getName()));

        return 0;
    }

    /**
     * @return \Generator|DocumentInterface[]
     */
    private function searchDocuments(Search $search): \Generator
    {
        foreach ($this->elasticaService->scroll($search) as $resultSet) {
            foreach ($resultSet as $result) {
                if ($result) {
                    yield Document::fromResult($result);
                }
            }
        }
    }
}

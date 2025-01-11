<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command\Release;

use EMS\CommonBundle\Common\Command\AbstractCommand;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Revision\Release\ReleaseRevisionType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\ReleaseService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: Commands::RELEASE_CREATE,
    description: 'Add documents for a given contenttype in a release.',
    hidden: false
)]
class CreateReleaseCommand extends AbstractCommand
{
    private ContentType $contentType;
    private Environment $target;
    /** @var array<mixed> */
    private array $query;

    private const string ARGUMENT_CONTENT_TYPE = 'contentType';
    private const string ARGUMENT_TARGET = 'target';
    private const string OPTION_QUERY = 'query';

    public function __construct(private readonly ReleaseService $releaseService, private readonly EnvironmentService $environmentService, private readonly ContentTypeService $contentTypeService, private readonly RevisionService $revisionService, private readonly ElasticaService $elasticaService)
    {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(self::ARGUMENT_CONTENT_TYPE, InputArgument::REQUIRED, 'ContentType')
            ->addArgument(self::ARGUMENT_TARGET, InputArgument::REQUIRED, 'Target managed alias name')
            ->addOption(self::OPTION_QUERY, null, InputOption::VALUE_OPTIONAL, 'ES query', '{}');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->target = $this->environmentService->giveByName($this->getArgumentString(self::ARGUMENT_TARGET));
        $this->contentType = $this->contentTypeService->giveByName($this->getArgumentString(self::ARGUMENT_CONTENT_TYPE));
        $this->query = Json::decode($this->getOptionString(self::OPTION_QUERY));
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->contentType->getEnvironment()) {
            throw new \RuntimeException('Unexpected null contenttype');
        }
        $this->io->title(\sprintf('EMSCO - Add Release for %s', $this->contentType));

        $search = $this->elasticaService->convertElasticsearchSearch([
            'index' => $this->contentType->getEnvironment()->getAlias(),
            '_source' => false,
            'type' => $this->contentType->getName(),
            'body' => \count($this->query) > 0 ? $this->query : null,
        ]);

        $documentCount = $this->elasticaService->count($search);
        if (0 === $documentCount) {
            $this->io->error(\count($this->query) > 0 ? \sprintf('No document found in %s with this query : %s', $this->contentType->getName(), Json::encode($this->query)) : \sprintf('No document found in %s', $this->contentType->getName()));

            return -1;
        }

        $this->io->comment(\count($this->query) > 0 ? \sprintf('%s document(s) found in %s with this query : %s', $documentCount, $this->contentType->getName(), Json::encode($this->query)) : \sprintf('%s document(s) found in %s', $documentCount, $this->contentType->getName()));

        $release = new Release();
        $release->setName(\sprintf('CMD-Release for %s to %s', $this->contentType, $this->target->getName()));

        $release->setEnvironmentSource($this->contentType->getEnvironment());
        $release->setEnvironmentTarget($this->target);

        $revisionCount = 0;
        foreach ($this->searchDocuments($search) as $document) {
            $revision = $this->revisionService->getByEmsLink(EMSLink::fromText($document->getEmsId()));
            if (null !== $revision && !$revision->isPublished($this->target->getName())) {
                $release->addRevision($revision, ReleaseRevisionType::PUBLISH);
                ++$revisionCount;
            }
        }

        if (0 === $revisionCount) {
            $this->io->error('No revisions to publish');

            return -1;
        }
        $this->io->comment(\sprintf('%s document(s) added to publish', $revisionCount));
        $this->releaseService->add($release);
        $this->io->success(\sprintf('Release %s has created', $release->getName()));

        return self::EXECUTE_SUCCESS;
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

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\QueryString;
use Elastica\Query\Terms;
use Elastica\Query\Wildcard;
use Elastica\Result;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\QueryStringEscaper;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\Mapping;
use EMS\CoreBundle\Service\ReleaseService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReleaseRevisionsUnpublishDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    public function __construct(
        private readonly ReleaseService $releaseService,
        private readonly RevisionService $revisionService,
        private readonly ElasticaService $elasticaService,
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace,
    ) {
    }

    #[\Override]
    public function build(QueryTable $table): void
    {
        /** @var array{'release': Release, 'environment': Environment} $context */
        $context = $table->getContext();
        $template = "@$this->templateNamespace/datatable/template_block_columns.html.twig";

        $table->setLabelAttribute('documentEmsId');
        $table->setIdField('documentEmsId');
        $table->setDefaultOrder(Mapping::FINALIZATION_DATETIME_FIELD, 'desc');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: 'release.revision.index.column.label',
            blockName: 'document_label',
            template: $template
        ));
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: 'release.revision.index.column.CT',
            blockName: 'document_content_type',
            template: $template,
            orderField: Mapping::CONTENT_TYPE_FIELD
        ));
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: 'release.revision.index.column.minRevId',
            blockName: 'release_unpublish_source',
            template: $template
        ));
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: 'release.revision.index.column.maxRevId',
            blockName: 'release_unpublish_target',
            template: $template,
            orderField: Mapping::FINALIZATION_DATETIME_FIELD
        ));

        $table->addTableAction(
            name: TableAbstract::ADD_ACTION,
            icon: 'fa fa-minus',
            labelKey: 'release.actions.add_unpublish',
            confirmationKey: 'release.revision.actions.add_confirm'
        );
        $table->addDynamicItemPostAction(
            route: Routes::RELEASE_ADD_REVISION,
            labelKey: 'release.revision.action.unpublish',
            icon: 'plus',
            messageKey: 'release.revision.actions.add_confirm',
            routeParameters: [
                'release' => (string) $context['release']->getId(),
                'type' => 'unpublish',
                'emsLinkToAdd' => 'documentEmsId',
            ]
        );
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }

    /**
     * @param array{'release_id': int} $options
     *
     * @return array{'release': Release, 'environment': Environment}
     */
    #[\Override]
    public function getContext(array $options): array
    {
        $release = $this->releaseService->getById($options['release_id']);

        return [
            'release' => $release,
            'environment' => $release->getEnvironmentTarget(),
            'exclude_ouuids' => $release->getRevisionsOuuids(),
        ];
    }

    #[\Override]
    public function getQueryName(): string
    {
        return 'release_revisions_unpublish';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $search = $this->search($context, $searchValue);
        $search->setFrom($from);
        $search->setSize($size);

        if ($orderField) {
            $search->setSort([$orderField => ['order' => $orderDirection]]);
        }

        $resultSet = $this->elasticaService->search($search);
        $documents = \array_map(static fn (Result $result) => Document::fromResult($result), $resultSet->getResults());
        $documentLinks = \array_map(static fn (Document $document) => $document->getEmsLink(), $documents);
        $infos = $this->revisionService->getDocumentsInfo(...$documentLinks);

        foreach ($documents as $document) {
            $document->setValue('[info]', $infos[$document->getEmsId()] ?? null);
        }

        return $documents;
    }

    #[\Override]
    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        $search = $this->search($context, $searchValue);

        return $this->elasticaService->count($search);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['release_id']);
    }

    /**
     * @param array{'release': Release, 'environment': Environment} $context
     */
    private function search(mixed $context, string $searchValue): Search
    {
        $ouuids = $context['release']->getRevisionsOuuids();

        $contentTypesGrantedPublication = $this->contentTypeService->getAllGrantedForPublication();

        $query = new BoolQuery();
        $query->addMust(new Terms(
            field: Mapping::CONTENT_TYPE_FIELD,
            terms: \array_values(\array_map(static fn (ContentType $contentType) => $contentType->getName(), $contentTypesGrantedPublication))
        ));

        if (\count($ouuids) > 0) {
            $query->addMustNot(new Terms('_id', \array_values($ouuids)));
        }

        if ('' !== $searchValue) {
            $searchValueEscaped = QueryStringEscaper::escape($searchValue);
            $searchValueQuery = new BoolQuery();
            $searchValueQuery
                ->setMinimumShouldMatch(1)
                ->addShould((new QueryString($searchValueEscaped))->setDefaultField('_all'))
                ->addShould(new MatchQuery('_all', $searchValue))
                ->addShould(new Wildcard('_all', \sprintf('*%s*', $searchValueEscaped)));

            $query->addMust($searchValueQuery);
        }

        $search = new Search([$context['environment']->getAlias()]);
        $search->setQueryArray($query->toArray());

        return $search;
    }
}

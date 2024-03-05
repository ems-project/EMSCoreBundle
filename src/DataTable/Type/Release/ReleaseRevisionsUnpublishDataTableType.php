<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractQueryTableType;
use EMS\CoreBundle\Core\Revision\Query\PublishedRevisionsQueryService;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReleaseRevisionsUnpublishDataTableType extends AbstractQueryTableType
{
    public function __construct(
        PublishedRevisionsQueryService $queryService,
        private readonly ReleaseService $releaseService,
        private readonly string $templateNamespace
    ) {
        parent::__construct($queryService);
    }

    public function build(QueryTable $table): void
    {
        /** @var Release $release */
        $release = $table->getContext()['release'];
        $template = "@$this->templateNamespace/release/columns/revisions.html.twig";

        $table->setMassAction(true);
        $table->setLabelAttribute('ems_link');
        $table->setIdField('ems_link');
        $table->setSelected($release->getRevisionsOuuids());

        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.label', 'unpublish_label', $template));
        $table->addColumn('release.revision.index.column.CT', 'content_type_label');
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'unpublish_source', $template));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'unpublish_target', $template));

        $table->addTableAction(TableAbstract::ADD_ACTION, 'fa fa-minus', 'release.actions.add_unpublish', 'release.revision.actions.add_confirm');
        $table->addDynamicItemPostAction(
            route: Routes::RELEASE_ADD_REVISION,
            labelKey: 'release.revision.action.unpublish',
            icon: 'plus',
            messageKey: 'release.revision.actions.add_confirm',
            routeParameters: [
                'release' => (string) $release->getId(),
                'type' => 'unpublish',
                'emsLinkToAdd' => 'ems_link',
            ]);
    }

    public function getQueryName(): string
    {
        return 'release_revisions_unpublish';
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }

    /**
     * @param array{'release_id': int} $options
     *
     * @return array{'release': Release, 'environment': Environment}
     */
    public function getContext(array $options): array
    {
        $release = $this->releaseService->getById($options['release_id']);

        return [
            'release' => $release,
            'environment' => $release->getEnvironmentTarget(),
            'exclude_ouuids' => $release->getRevisionsOuuids(),
        ];
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['release_id']);
    }
}

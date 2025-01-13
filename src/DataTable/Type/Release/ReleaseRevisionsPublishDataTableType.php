<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractQueryTableType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReleaseRevisionsPublishDataTableType extends AbstractQueryTableType
{
    public function __construct(
        ReleaseRevisionService $releaseRevisionService,
        private readonly ReleaseService $releaseService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($releaseRevisionService);
    }

    #[\Override]
    public function build(QueryTable $table): void
    {
        /** @var Release $release */
        $release = $table->getContext();
        $template = "@$this->templateNamespace/release/columns/revisions.html.twig";

        $table->setMassAction(true);
        $table->setLabelAttribute('item_labelField');
        $table->setIdField('emsLink');
        $table->setSelected($release->getRevisionsOuuids());

        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.label', 'publish_label', $template));
        $table->addColumn('release.revision.index.column.CT', 'content_type_singular_name');
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.minRevId', 'minrevid', $template));
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.revision.index.column.maxRevId', 'maxrevid', $template));

        $table->addTableAction(TableAbstract::ADD_ACTION, 'fa fa-plus', 'release.actions.add_publish', 'release.revision.actions.add_confirm');
        $table->addDynamicItemPostAction(
            route: Routes::RELEASE_ADD_REVISION,
            labelKey: 'release.revision.action.publish',
            icon: 'plus',
            messageKey: 'release.revision.actions.add_confirm',
            routeParameters: [
                'release' => (string) $release->getId(),
                'type' => 'publish',
                'emsLinkToAdd' => 'emsLink',
            ]
        );
    }

    #[\Override]
    public function getQueryName(): string
    {
        return 'revisions-to-publish';
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }

    #[\Override]
    public function getContext(array $options): Release
    {
        return $this->releaseService->getById($options['release_id']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['release_id']);
    }
}

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

use function Symfony\Component\Translation\t;

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
        $template = \sprintf('@%s/release/columns/revisions.html.twig', $this->templateNamespace);

        $table->setMassAction(true);
        $table->setLabelAttribute('item_labelField');
        $table->setIdField('emsLink');
        $table->setSelected($release->getRevisionsOuuids());

        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.label', [], 'emsco-core'), 'publish_label', $template));
        $table->addColumn(t('release.revision.index.column.CT', [], 'emsco-core'), 'content_type_singular_name');
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.minRevId', [], 'emsco-core'), 'minrevid', $template));
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.maxRevId', [], 'emsco-core'), 'maxrevid', $template));

        $table->addTableAction(
            name: TableAbstract::ADD_ACTION,
            icon: 'fa fa-plus',
            labelKey: t('release.actions.add_publish', [], 'emsco-core'),
            confirmationKey: t('release.revision.actions.add_confirm', [], 'emsco-core')
        );
        $table->addDynamicItemPostAction(
            route: Routes::RELEASE_ADD_REVISION,
            labelKey: t('release.revision.action.publish', [], 'emsco-core'),
            icon: 'plus',
            messageKey: t('release.revision.actions.add_confirm', [], 'emsco-core'),
            routeParameters: [
                'release' => (string) $release->getId(),
                'type' => 'publish',
                'emsLinkToAdd' => 'emsLink',
            ],
            attributes: ['data-testid' => 'release-action-add-for-publish']
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

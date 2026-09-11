<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseRevisionService;
use EMS\CoreBundle\Service\ReleaseService;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class ReleaseRevisionDataTableType extends AbstractEntityTableType
{
    public const ACTION_ROLLBACK = 'rollback_action';

    public function __construct(
        ReleaseRevisionService $releaseRevisionService,
        private readonly ReleaseService $releaseService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($releaseRevisionService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        /** @var Release $release */
        $release = $table->getContext();

        $table->setMassAction(true);
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.CT', [], 'emsco-core'), 'contentType', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.document', [], 'emsco-core'), 'label', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.revision', [], 'emsco-core'), 'revision', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));
        $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.action', [], 'emsco-core'), 'action', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));

        switch ($release->getStatus()) {
            case Release::WIP_STATUS:
                $table->addTableAction(TableAbstract::REMOVE_ACTION, 'fa fa-minus', t('release.revision.actions.remove', [], 'emsco-core'), t('release.revision.actions.remove_confirm', [], 'emsco-core'));
                break;
            case Release::APPLIED_STATUS:
                $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.still_in_target', ['target' => $release->getEnvironmentTarget()->getLabel()], 'emsco-core'), 'stil_in_target', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));
                $table->addColumnDefinition(new TemplateBlockTableColumn(t('release.revision.index.column.previous', [], 'emsco-core'), 'previous', \sprintf('@%s/release/columns/release-revisions.html.twig', $this->templateNamespace)));
                $table->addDynamicItemGetAction(
                    route: Routes::VIEW_REVISIONS,
                    labelKey: t('release.revision.index.column.compare', [], 'emsco-core'),
                    icon: 'compress',
                    routeParameters: ['type' => 'contentType', 'ouuid' => 'revisionOuuid', 'revisionId' => 'revision.id', 'compareId' => 'rollbackRevision.id'],
                    attributes: ['data-testid' => 'release-revision-compare']
                )->addCondition(new NotEmpty('rollbackRevision', 'revision'));
                $table->addTableAction(
                    name: self::ACTION_ROLLBACK,
                    icon: 'fa fa-rotate-left',
                    labelKey: t('release.revision.table.rollback.action', [], 'emsco-core'),
                    confirmationKey: t('release.revision.table.rollback.confirm', [], 'emsco-core')
                );
                break;
        }
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

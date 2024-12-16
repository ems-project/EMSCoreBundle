<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Release;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ReleaseService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class ReleasePickDataTableType extends AbstractEntityTableType
{
    public function __construct(
        ReleaseService $releaseService,
        private readonly RevisionService $revisionService,
        private readonly string $templateNamespace,
    ) {
        parent::__construct($releaseService);
    }

    public function build(EntityTable $table): void
    {
        /** @var Revision $revision */
        $revision = $table->getContext();

        $table->setDefaultOrder('executionDate', 'desc');
        $table->addColumn(
            titleKey: t('field.name', [], 'emsco-core'),
            attribute: 'name'
        );
        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_execution', [], 'emsco-core'),
            attribute: 'executionDate')
        );
        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.status', [], 'emsco-core'),
            blockName: 'status',
            template: "@$this->templateNamespace/release/columns/revisions.html.twig")
        );
        $table->addColumnDefinition(new TemplateBlockTableColumn('release.index.column.docs_count', 'docs_count', "@$this->templateNamespace/release/columns/revisions.html.twig"))->setCellClass('text-right');

        $table->addItemPostAction(Routes::DATA_ADD_REVISION_TO_RELEASE, 'data.actions.add_to_release_publish', 'plus', 'data.actions.add_to_release_confirm', ['revision' => $revision->getId(), 'type' => 'publish'])->setButtonType('primary');
        $table->addItemPostAction(Routes::DATA_ADD_REVISION_TO_RELEASE, 'data.actions.add_to_release_unpublish', 'minus', 'data.actions.add_to_release_confirm', ['revision' => $revision->getId(), 'type' => 'unpublish'])->setButtonType('default');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_PUBLISHER];
    }

    public function getContext(array $options): Revision
    {
        return $this->revisionService->getByRevisionId($options['revision_id']);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['revision_id']);
    }
}

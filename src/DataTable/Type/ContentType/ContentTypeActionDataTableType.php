<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ActionService;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class ContentTypeActionDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(
        ActionService $entityService,
        private readonly ContentTypeService $contentTypeService,
    ) {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();
        $routeParams = ['contentType' => $contentType->getId()];

        $this->addColumnsOrderLabelName($table);
        $table->getColumnByName('label')?->setItemIconCallback(fn (Template $action) => $action->getIcon());
        $table->addColumn(t('field.type', [], 'emsco-core'), 'renderOption');

        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.public_access', [], 'emsco-core'),
            attribute: 'public'
        ));

        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addItemEdit($table, Routes::ADMIN_CONTENT_TYPE_ACTION_EDIT, $routeParams)
            ->addItemDelete($table, 'content_type_action', Routes::ADMIN_CONTENT_TYPE_ACTION_DELETE, $routeParams)
            ->addTableToolbarActionAdd($table, Routes::ADMIN_CONTENT_TYPE_ACTION_ADD, $routeParams)
            ->addTableActionDelete($table, 'content_type_action');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    #[\Override]
    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }
}

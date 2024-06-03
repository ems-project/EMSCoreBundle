<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\Type\AbstractQueryTableType;
use EMS\CoreBundle\Core\Revision\RevisionQueryService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RevisionTrashDataTableType extends AbstractQueryTableType
{
    public const ACTION_EMPTY_TRASH = 'empty-trash';
    public const ACTION_PUT_BACK = 'put-back';

    public function __construct(
        RevisionQueryService $queryService,
        private readonly UserService $userService,
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($queryService);
    }

    public function build(QueryTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext()['content_type'];

        $table->setIdField('ouuid');
        $table->setExtraFrontendOption(['searching' => false]);

        if ($this->userService->isSuper()) {
            $table->addColumn('revision.property.ouuid', 'ouuid');
        }

        $table->addColumn('revision.property.label', 'revision_label');
        $table->addColumnDefinition(new UserTableColumn('revision.property.deleted_by', 'deleted_by'));
        $table->addColumnDefinition(new DatetimeTableColumn('revision.property.modified', 'modified'));

        $table->setLabelAttribute('revision_label');

        if ($this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::CREATE))) {
            $table->addDynamicItemPostAction(
                route: Routes::DATA_TRASH_PUT_BACK,
                labelKey: 'revision.trash.put_back',
                icon: 'recycle',
                messageKey: 'revision.trash.put_back_confirm',
                routeParameters: [
                    'contentType' => 'content_type_id',
                    'ouuid' => 'ouuid',
                ]
            );
            $table->addTableAction(
                name: self::ACTION_PUT_BACK,
                icon: 'fa fa-recycle',
                labelKey: 'revision.trash.put_back_selected'
            );
        }

        $table->addDynamicItemPostAction(
            route: Routes::DATA_TRASH_EMPTY,
            labelKey: 'revision.trash.empty',
            icon: 'trash',
            messageKey: 'revision.trash.empty_confirm',
            routeParameters: [
                'contentType' => 'content_type_id',
                'ouuid' => 'ouuid',
            ]
        )->setButtonType('outline-danger');
        $table->addTableAction(
            name: self::ACTION_EMPTY_TRASH,
            icon: 'fa fa-trash',
            labelKey: 'revision.trash.empty_selected'
        )->setCssClass('btn btn-outline-danger');
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver
            ->setDefault('translation_domain', EMSCoreBundle::TRANS_REVISION)
            ->setRequired(['content_type_name']);
    }

    /**
     * @param array{'content_type_name': string} $options
     *
     * @return array{'content_type': ContentType, 'deleted'?: bool, 'current'?: bool}
     */
    public function getContext(array $options): array
    {
        return [
            'content_type' => $this->contentTypeService->giveByName($options['content_type_name']),
            'deleted' => true,
            'current' => true,
        ];
    }

    public function getQueryName(): string
    {
        return 'revision_trash';
    }
}

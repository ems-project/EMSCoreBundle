<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\Condition\Terms;
use EMS\CoreBundle\Form\Data\DataLinksTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\RolesTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\UserService;

class UserDataTableType extends AbstractEntityTableType
{
    public function __construct(
        UserService $entityService,
        private readonly ?string $circleObject,
    ) {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn('user.index.column.username', 'username');
        $table->addColumn('user.index.column.displayname', 'displayName');
        $table->addColumnDefinition(new BoolTableColumn('user.index.column.email_notification', 'emailNotification'))
            ->setIconClass('fa fa-bell');
        $table->addColumn('user.index.column.email', 'email');
        $table->addColumn('user.index.column.locale_ui', 'locale');
        $table->addColumn('user.index.column.locale_preferred', 'localePreferred');
        $table->addColumn('user.index.column.wysiwyg_profile', 'wysiwygProfile');
        if ($this->circleObject) {
            $table->addColumnDefinition(new DataLinksTableColumn('user.index.column.circles', 'circles'));
        }
        $table->addColumnDefinition(new BoolTableColumn('user.index.column.enabled', 'enabled'));
        $table->addColumnDefinition(new RolesTableColumn('user.index.column.roles', 'roles'));
        $table->addColumnDefinition(new DatetimeTableColumn('user.index.column.lastLogin', 'lastLogin'));
        $table->addColumnDefinition(new DatetimeTableColumn('user.index.column.expirationDate', 'expirationDate'));

        $table->addDynamicItemGetAction(Routes::USER_EDIT, 'user.action.edit', 'pencil', ['user' => 'id']);
        $table->addDynamicItemGetAction('homepage', 'user.action.switch', 'user-secret', ['_switch_user' => 'username']);
        $table->addDynamicItemPostAction(Routes::USER_ENABLING, 'user.action.disable', 'user-times', 'user.action.disable_confirm', ['user' => 'id']);
        $table->addDynamicItemPostAction(Routes::USER_API_KEY, 'user.action.generate_api', 'key', 'user.action.generate_api_confirm', ['username' => 'username'])->addCondition(new Terms('roles', [Roles::ROLE_API]));
        $table->addDynamicItemPostAction(Routes::USER_DELETE, 'user.action.delete', 'trash', 'user.action.delete_confirm', ['user' => 'id']);
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_USER_MANAGEMENT];
    }
}

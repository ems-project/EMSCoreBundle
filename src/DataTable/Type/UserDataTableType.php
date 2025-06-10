<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\User\UserContextDTO;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\Condition\Terms;
use EMS\CoreBundle\Form\Data\DataLinksTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\EntityTableColumn;
use EMS\CoreBundle\Form\Data\RolesTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Standard\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserDataTableType extends AbstractEntityTableType
{
    public function __construct(
        UserService $entityService,
        private readonly ?string $circleObject,
        private readonly bool $groupFeature,
    ) {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumn('user.index.column.username', 'username');
        $table->addColumn('user.index.column.displayname', 'displayName');
        $table->addColumn('user.index.column.email', 'email');
        $context = $table->getContext();
        if (!$context->inGroup && $context->light && $this->groupFeature) {
            $table->addColumnDefinition(new EntityTableColumn('user.index.column.group', 'group'));
        }
        if ($context instanceof UserContextDTO && $context->inGroup && null !== $context->groupId) {
            $table->addDynamicItemPostAction(Routes::USER_REMOVE_FROM_GROUP, 'user.action.remove', 'trash', 'user.action.remove_confirm', ['user' => 'id', 'groupName' => $context->groupId]);
        }
        if ($context instanceof UserContextDTO && !$context->inGroup && null !== $context->groupId) {
            $table->addDynamicItemGetAction(Routes::USER_ADD_TO_GROUP, 'user.add.button', 'plus', ['user' => 'id', 'group' => $context->groupId]);
        }
        if (!$context instanceof UserContextDTO || !$context->light) {
            $table->addColumnDefinition(new BoolTableColumn('user.index.column.email_notification', 'emailNotification'))
                ->setIconClass('fa fa-bell');
            $table->addColumn('user.index.column.locale_ui', 'locale');
            $table->addColumn('user.index.column.locale_preferred', 'localePreferred');
            $table->addColumn('user.index.column.wysiwyg_profile', 'wysiwygProfile');
            if ($this->circleObject) {
                $table->addColumnDefinition(new DataLinksTableColumn('user.index.column.circles', 'circles'));
            }
            $table->addColumnDefinition(new BoolTableColumn('user.index.column.enabled', 'enabled'));
            if ($this->groupFeature) {
                $table->addColumnDefinition(new EntityTableColumn('user.index.column.group', 'group'));
            }
            $table->addColumnDefinition(new RolesTableColumn('user.index.column.roles', 'roles'));
            $table->addColumnDefinition(new DatetimeTableColumn('user.index.column.lastLogin', 'lastLogin'));
            $table->addColumnDefinition(new DatetimeTableColumn('user.index.column.expirationDate', 'expirationDate'));

            $table->addDynamicItemGetAction(Routes::USER_EDIT, 'user.action.edit', 'pencil', ['user' => 'id']);
            $table->addDynamicItemGetAction('homepage', 'user.action.switch', 'user-secret', ['_switch_user' => 'username']);
            $table->addDynamicItemPostAction(Routes::USER_ENABLING, 'user.action.disable', 'user-times', 'user.action.disable_confirm', ['user' => 'id']);
            $table->addDynamicItemPostAction(Routes::USER_API_KEY, 'user.action.generate_api', 'key', 'user.action.generate_api_confirm', ['username' => 'username'])->addCondition(new Terms('roles', [Roles::ROLE_API]));
            $table->addDynamicItemPostAction(Routes::USER_DELETE, 'user.action.delete', 'trash', 'user.action.delete_confirm', ['user' => 'id']);
        }
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_USER_MANAGEMENT];
    }

    #[\Override]
    public function getContext(array $options): UserContextDTO
    {
        return new UserContextDTO(
            Type::bool($options['light'] ?? null),
            Type::bool($options['in-group'] ?? null),
            Type::nullableString($options['group-id'] ?? null)
        );
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        parent::configureOptions($optionsResolver);
        $optionsResolver->setDefaults([
            'light' => false,
            'in-group' => false,
            'group-id' => null,
        ])->setAllowedTypes('light', ['bool'])->setAllowedTypes('group-id', ['null', 'string'])->setAllowedTypes('in-group', ['bool']);
    }
}

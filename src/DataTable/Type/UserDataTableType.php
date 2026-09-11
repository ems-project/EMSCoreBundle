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

use function Symfony\Component\Translation\t;

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
        $table->addColumn(t('user.index.column.username', [], 'emsco-core'), 'username');
        $table->addColumn(t('user.index.column.displayname', [], 'emsco-core'), 'displayName');
        $table->addColumn(t('user.index.column.email', [], 'emsco-core'), 'email');

        $context = $table->getContext();
        if (!$context->inGroup && $context->light && $this->groupFeature) {
            $table->addColumnDefinition(new EntityTableColumn(t('user.index.column.group', [], 'emsco-core'), 'group'));
        }
        if ($context instanceof UserContextDTO && $context->inGroup && null !== $context->groupId) {
            $table->addDynamicItemPostAction(
                route: Routes::USER_REMOVE_FROM_GROUP,
                labelKey: t('user.action.remove', [], 'emsco-core'),
                icon: 'trash',
                messageKey: t('user.action.remove_confirm', [], 'emsco-core'),
                routeParameters: ['user' => 'id', 'groupName' => $context->groupId]
            );
        }
        if ($context instanceof UserContextDTO && !$context->inGroup && null !== $context->groupId) {
            $table->addDynamicItemGetAction(
                route: Routes::USER_ADD_TO_GROUP,
                labelKey: t('user.add.button', [], 'emsco-core'),
                icon: 'plus',
                routeParameters: ['user' => 'id', 'group' => $context->groupId]
            );
        }
        if (!$context instanceof UserContextDTO || !$context->light) {
            $table->addColumnDefinition(new BoolTableColumn(t('user.index.column.email_notification', [], 'emsco-core'), 'emailNotification'))
                ->setIconClass('fa fa-bell');
            $table->addColumn(t('user.index.column.locale_ui', [], 'emsco-core'), 'locale');
            $table->addColumn(t('user.index.column.locale_preferred', [], 'emsco-core'), 'localePreferred');
            $table->addColumn(t('user.index.column.wysiwyg_profile', [], 'emsco-core'), 'wysiwygProfile');
            if ($this->circleObject) {
                $table->addColumnDefinition(new DataLinksTableColumn(t('user.index.column.circles', [], 'emsco-core'), 'circles'));
            }
            $table->addColumnDefinition(new BoolTableColumn(t('user.index.column.enabled', [], 'emsco-core'), 'enabled'));
            if ($this->groupFeature) {
                $table->addColumnDefinition(new EntityTableColumn(t('user.index.column.group', [], 'emsco-core'), 'group'));
            }
            $table->addColumnDefinition(new RolesTableColumn(t('user.index.column.roles', [], 'emsco-core'), 'roles'));
            $table->addColumnDefinition(new DatetimeTableColumn(t('user.index.column.lastLogin', [], 'emsco-core'), 'lastLogin'));
            $table->addColumnDefinition(new DatetimeTableColumn(t('user.index.column.expirationDate', [], 'emsco-core'), 'expirationDate'));

            $table->addDynamicItemGetAction(
                route: Routes::USER_EDIT,
                labelKey: t('user.action.edit', [], 'emsco-core'),
                icon: 'pencil',
                routeParameters: ['user' => 'id'],
                attributes: ['data-testid' => 'user-action-edit']
            );
            $table->addDynamicItemGetAction(
                route: 'homepage',
                labelKey: t('user.action.switch', [], 'emsco-core'),
                icon: 'user-secret',
                routeParameters: ['_switch_user' => 'username'],
                attributes: ['data-testid' => 'user-action-switch-user']
            );
            $table->addDynamicItemPostAction(
                route: Routes::USER_ENABLING,
                labelKey: t('user.action.disable', [], 'emsco-core'),
                icon: 'user-times',
                messageKey: t('user.action.disable_confirm', [], 'emsco-core'),
                routeParameters: ['user' => 'id'],
                attributes: ['data-testid' => 'user-action-disabled']
            );
            $table->addDynamicItemPostAction(
                route: Routes::USER_API_KEY,
                labelKey: t('user.action.generate_api', [], 'emsco-core'),
                icon: 'key',
                messageKey: t('user.action.generate_api_confirm', [], 'emsco-core'),
                routeParameters: ['username' => 'username'],
                attributes: ['data-testid' => 'user-action-generate-api']
            )->addCondition(new Terms('roles', [Roles::ROLE_API]));
            $table->addDynamicItemPostAction(
                route: Routes::USER_DELETE,
                labelKey: t('user.action.delete', [], 'emsco-core'),
                icon: 'trash',
                messageKey: t('user.action.delete_confirm', [], 'emsco-core'),
                routeParameters: ['user' => 'id'],
                attributes: ['data-testid' => 'user-action-delete']
            );
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

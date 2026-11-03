<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class RolePickerType extends ChoiceType
{
    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver
            ->setDefaults([
                'label' => t('field.role', [], 'emsco-core'),
                'include_not_defined' => true,
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'select2',
                ],
                'choice_attr' => fn () => [
                    'data-icon' => 'fa fa-user-circle',
                ],
                'choice_value' => fn ($value) => $value,
                'choice_label' => fn (string $role) => Roles::translate($role),
            ])
            ->setAllowedTypes('include_not_defined', 'bool')
            ->setDefault('choices', function (Options $options) {
                return [
                    ...($options['include_not_defined'] ? [Roles::NOT_DEFINED] : []),
                    ...$this->userService->listUserRoles(),
                ];
            });
    }
}

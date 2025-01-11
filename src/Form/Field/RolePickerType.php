<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RolePickerType extends Select2Type
{
    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [...['role.not-defined' => Roles::NOT_DEFINED], ...$this->userService->listUserRoles()];

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => ['data-live-search' => true],
            'choice_attr' => fn () => [
                'data-icon' => 'fa fa-user-circle',
            ],
            'choice_value' => fn ($value) => $value,
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'choice_translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}

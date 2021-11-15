<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RolePickerType extends SelectPickerType
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->userService = $userService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = \array_merge(['role.not-defined' => null], $this->userService->listUserRoles());

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => ['data-live-search' => true],
            'choice_attr' => function () {
                return [
                    'data-icon' => 'fa fa-square',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
        ]);
    }
}

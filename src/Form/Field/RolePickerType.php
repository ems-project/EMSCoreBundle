<?php

namespace EMS\CoreBundle\Form\Field;

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

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = \array_merge(['not-defined' => 'not-defined'], $this->userService->listUserRoles());

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => ['data-live-search' => true],
            'choice_attr' => function ($category, $key, $index) {
                //TODO: it would be nice to translate the roles
                return [
                        'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$this->humanize($key).'</div>',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RolePickerType extends SelectPickerType
{
    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->userService = $userService;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $choices = $this->getExistingRoles();

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => [
                    'data-live-search' => true,
            ],
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

    private function getExistingRoles()
    {
        $roleHierarchy = $this->userService->getsecurityRoles();
        $roles = \array_keys($roleHierarchy);

        $theRoles['not-defined'] = 'not-defined';
        $theRoles['ROLE_USER'] = 'ROLE_USER';

        foreach ($roles as $role) {
            $theRoles[$role] = $role;
        }
        $theRoles['ROLE_API'] = 'ROLE_API';

        return $theRoles;
    }
}

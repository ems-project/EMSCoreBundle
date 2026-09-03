<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class RoleMultiPickerType extends ChoiceType
{
    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'label' => t('field.roles', [], 'emsco-core'),
            'choices' => $this->userService->listUserRoles(),
            'multiple' => true,
            'expanded' => true,
            'choice_label' => fn (string $role) => Roles::translate($role),
        ]);
    }
}

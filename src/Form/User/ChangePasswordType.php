<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('current_password', PasswordType::class, [
            'label' => 'user.current_password',
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new UserPassword(['message' => 'user.current_password.invalid']),
            ],
            'attr' => ['autocomplete' => 'current-password'],
        ]);

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => [
                'attr' => ['autocomplete' => 'new-password',
                ], ],
            'first_options' => ['label' => 'user.new_password'],
            'second_options' => ['label' => 'user.new_password_confirmation'],
            'invalid_message' => 'user.password.mismatch',
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'change_password',
            'data_class' => User::class,
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'validation_groups' => ['ChangePassword', 'Default'],
        ]);
    }
}

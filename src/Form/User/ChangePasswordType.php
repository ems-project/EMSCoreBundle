<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ChangePasswordType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('current_password', PasswordType::class, [
            'label' => t('key.current_password', [], 'emsco-core'),
            'mapped' => false,
            'constraints' => [
                new NotBlank(),
                new UserPassword(message: 'user.current_password_invalid'),
            ],
            'attr' => ['autocomplete' => 'current-password'],
        ]);

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'options' => [
                'attr' => ['autocomplete' => 'new-password',
                ], ],
            'first_options' => ['label' => t('key.new_password', [], 'emsco-core')],
            'second_options' => ['label' => t('key.new_password_confirmation', [], 'emsco-core')],
            'invalid_message' => t('user.password.mismatch', [], 'validators'),
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'change_password',
            'data_class' => User::class,
        ]);
    }
}

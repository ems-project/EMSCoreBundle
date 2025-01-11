<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\User;

use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResettingRequestType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username_email', null, [
                'constraints' => [new NotBlank()],
                'label' => 'user.resetting.username_email',
            ])
            ->add('submit', SubmitType::class, ['label' => 'user.resetting.title'])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => EMSCoreBundle::TRANS_USER_DOMAIN,
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobType extends AbstractType
{
    /**
     * @param FormBuilderInterface<Form> $builder
     * @param array<mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('command', TextType::class, [
                'required' => false
            ])
            ->add('launch', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
        ]);
    }
}

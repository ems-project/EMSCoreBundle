<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ChannelType extends AbstractType
{
    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['required' => true])
            ->add('slug', null, ['required' => true])
            ->add('public', CheckboxType::class, ['required' => false])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
            'label_format' => 'form.form.channel.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Form\DataTransformer\ChannelOptionsTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Subform\ChannelOptionsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class ChannelType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('name', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('alias', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('public', CheckboxType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
            ->add('options', ChannelOptionsType::class)
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
        $builder->get('options')->addModelTransformer(new ChannelOptionsTransformer());
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Channel::class,
            'label_format' => 'form.form.channel.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}

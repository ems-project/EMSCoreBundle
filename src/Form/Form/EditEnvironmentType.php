<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditEnvironmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Revision $revision */
        $revision = $builder->getData();

        $builder
        ->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag',
        ])
        ->add('color', ColorPickerType::class, [
                'required' => false,
        ]);
        if (\array_key_exists('type', $options) && $options['type']) {
            $builder->add('circles', ObjectPickerType::class, [
                    'required' => false,
                    'type' => $options['type'],
                    'multiple' => true,
            ]);
        }
        $builder->add('baseUrl', TextType::class, [
                'required' => false,
        ])->add('inDefaultSearch', CheckboxType::class, [
            'required' => false,
        ])->add('extra', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => '6',
            ],
        ])
        ->add('save', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefault('type', null);
    }
}

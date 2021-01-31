<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AssetType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple'] ?? false) {
            $builder->add('files', CollectionType::class, [
                'entry_type' => AssetType::class,
                'entry_options' => [
                    'multiple' => false
                ],
                'allow_add' => true,
                'prototype' => true,
            ]);
        } else {
            $builder->add('sha1', HiddenType::class, [
                'attr' => [
                    'class' => 'sha1',
                ],
                'required' => $options['required'],
            ])
            ->add('mimetype', TextType::class, [
                'attr' => [
                    'class' => 'type',
                ],
                'required' => $options['required'],
            ])
            ->add('filename', TextType::class, [
                'attr' => [
                    'class' => 'name',
                ],
                'required' => $options['required'],
            ]);
        }

    }


    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('multiple', false);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'assettype';
    }
}

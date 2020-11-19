<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class AssetType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'assettype';
    }
}

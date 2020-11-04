<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Field\ViewTypePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

class ViewType extends AbstractType
{
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        /** @var Template $template */
        $template = $builder->getData();
        
        $builder
        ->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag'
        ])
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ])
        ->add('type', ViewTypePickerType::class, [
            'required' => false,
        ])
        ->add('public', CheckboxType::class, [
            'required' => false,
        ])
        ->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm',
                ],
                'icon' => 'fa fa-save'
        ]);
    }
}

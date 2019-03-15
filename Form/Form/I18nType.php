<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\I18nContentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class I18nType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('identifier', null, array('required' => true))
            ->add(
                'content',
                CollectionType::class,
                array(
                    'entry_type'   => I18nContentType::class,
                    'allow_add' => true,
                    'label' => false,
                    'delete_empty' => true,
                    'allow_delete' => true,
                )
            );
    }
    
    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'EMS\CoreBundle\Entity\I18n'
        ));
    }
}

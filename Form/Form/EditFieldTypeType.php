<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\EditFieldType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditFieldTypeType extends AbstractType {
    /**
     *
     * @param FormBuilderInterface $builder
     * @param array $options            
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        
        
        /** @var EditFieldType $editFieldType */
        $editFieldType = $builder->getData ();

        $builder->add ( 'fieldType', FieldTypeType::class, [
            'data' => $editFieldType->getFieldType(),
            'editSubfields' => false,
        ]);
        
        
        $builder->add ( 'save', SubmitEmsType::class, [ 
            'attr' => [
                'class' => 'btn-primary btn-sm '
            ],
            'icon' => 'fa fa-save'
        ] );
        $builder->add ( 'saveAndClose', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn-primary btn-sm '
            ],
            'icon' => 'fa fa-save'
        ] );
        
        return parent::buildForm($builder, $options);
         
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver) {
//         $resolver->setDefault ( 'twigWithWysiwyg', true );
    }
    
}

<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeStructureType extends AbstractType {
    /**
     *
     * @param FormBuilderInterface $builder            
     * @param array $options            
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        
        
        /** @var ContentType $contentType */
        $contentType = $builder->getData ();

        if($contentType->getEnvironment()->getManaged()){
            $builder->add ( 'fieldType', FieldTypeType::class, [
                'data' => $contentType->getFieldType()
            ]);            
        }
        
        
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
        $builder->add ( 'saveAndReorder', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-reorder'
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

<?php 

namespace EMS\CoreBundle\Form\Form;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class EmsCollectionType extends CollectionType{

    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    
    public function __construct(AuthorizationCheckerInterface $authorizationChecker) {
        $this->authorizationChecker = $authorizationChecker;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Symfony\Component\Form\Extension\Core\Type\CollectionType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        /** @var FieldType $fieldType */
        $fieldType = clone $builder->getOptions () ['metadata'];
        $options['metadata'] = $fieldType;
        
        $entryOptions = $fieldType->getDisplayOptions();
        

        $disabled = false;
        if(strcmp('cli', php_sapi_name()) !== 0){
            $enable = ($options['migration'] && !$fieldType->getMigrationgOption('protected', true)) || $this->authorizationChecker->isGranted($fieldType->getMinimumRole());
            $disabled = !$enable;
        }
        
        $options = array_merge($options, [
                'entry_type' => CollectionItemFieldType::class,
                'entry_options' => [
                    'metadata' => $fieldType,
                    'migration' => $options['migration'],
                    'raw_data' =>  $options['raw_data'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'disabled' => $disabled,
        ]);
        

        parent::buildForm($builder, $options);
    }
    
    public function configureOptions(OptionsResolver $resolver){
        
        /* set the default option value for this kind of compound field */
        parent::configureOptions ( $resolver );
        $resolver->setDefaults([
                'collapsible' => false,
                'icon' => null,
                'itemBootstrapClass' => null,
                'singularLabel' => null,
                'sortable' => false,
        ]);
    }
    
}
<?php 

namespace EMS\CoreBundle\Form\FieldType;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\CollectionItemFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\DataField\SubfieldType;
use EMS\CoreBundle\Form\Field\FieldTypePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldTypeType extends AbstractType
{
	/** @var FieldTypePickerType $fieldTypePickerType */
	private $fieldTypePickerType;
	
	public function __construct(FieldTypePickerType $fieldTypePickerType) {
		$this->fieldTypePickerType = $fieldTypePickerType;
	}
	

	/**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	
    	/** @var FieldType $fieldType */
    	$fieldType = $options['data'];

    	$builder->add ( 'name', HiddenType::class ); 
    	
    	$type = $fieldType->getType();
    	$dataFieldType = new $type;
    	
    	
    	$dataFieldType->buildOptionsForm($builder, $options);
    	
    	
    	if($dataFieldType->isContainer()) {
	    	$builder->add ( 'ems:internal:add:field:class', FieldTypePickerType::class, [
	    			'label' => 'Field\'s type',
	    			'mapped' => false,
	    			'required' => false
	    	]);    	
	    	$builder->add ( 'ems:internal:add:field:name', TextType::class, [
	    			'label' => 'Field\'s machine name',
	    			'mapped' => false,
	    			'required' => false,
	    	]);

	    	$builder->add ( 'add', SubmitEmsType::class, [
	    			'attr' => [
	    					'class' => 'btn-primary '
	    			],
	    			'icon' => 'fa fa-plus'
	    	] );

    	}
    	else if(strcmp(SubfieldType::class, $fieldType->getType()) !=0 ) {

    		$builder->add ( 'ems:internal:add:subfield:name', TextType::class, [
    				'label' => 'Subfield\'s name',
    				'mapped' => false,
    				'required' => false,
    		]);
    		
    		$builder->add ( 'subfield', SubmitEmsType::class, [
    				'label' => 'Add',
    				'attr' => [
    						'class' => 'btn-primary '
    				],
    				'icon' => 'fa fa-plus'
    		] );
    		
	    	$builder->add ( 'ems:internal:add:subfield:target_name', TextType::class, [
	    			'label' => 'New field\'s machine name',
	    			'mapped' => false,
	    			'required' => false,
	    	]);
	    	
	    	$builder->add ( 'duplicate', SubmitEmsType::class, [
	    			'label' => 'Duplicate',
    				'attr' => [
    						'class' => 'btn-primary '
    				],
    				'icon' => 'fa fa-paste'
    		] );    		
    	}
    	if(null != $fieldType->getParent()){
	    	$builder->add ( 'remove', SubmitEmsType::class, [
	    			'attr' => [
	    					'class' => 'btn-danger btn-xs'
	    			],
	    			'icon' => 'fa fa-trash'
	    	] );	    		
    	}

    	if(isset($fieldType) && null != $fieldType->getChildren() && $fieldType->getChildren()->count() > 0){

    		$childFound = false;
			/** @var FieldType $field */
			foreach ($fieldType->getChildren() as $idx => $field) {
				if(!$field->getDeleted()){
					$childFound = true;
					$builder->add ( 'ems_'.$field->getName(), FieldTypeType::class, [
							'data' => $field,
							'container' => true,
					]  );						
				}
			}
			
			if($childFound) {
				$builder->add ( 'reorder', SubmitEmsType::class, [
						'attr' => [
								'class' => 'btn-primary '
						],
						'icon' => 'fa fa-reorder'
				] );				
			}
    	}
    }   

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'EMS\CoreBundle\Entity\FieldType',
        	'container' => false,
        	'path' => false,
        	'new_field' => false,
        ));
    }
	
    public function dataFieldToArray(DataField $dataField){
    	$out = [];
    	
    	
    	$dataFieldType = new CollectionItemFieldType();
    	/** @var DataFieldType $dataFieldType */
    	if(null != $dataField->getFieldType()){
	    	$dataFieldType = $this->fieldTypePickerType->getDataFieldType($dataField->getFieldType()->getType());    		
    	}
    	 
    	$dataFieldType->buildObjectArray($dataField, $out);
    	
    	

    	/** @var DataField $child */
    	foreach ( $dataField->getChildren () as $child ) {
    		//its a Collection Item
	    	if ($child->getFieldType() == null){
	    		$subOut = [];
	    		foreach ( $child->getChildren () as $grandchild ) {
	    			$subOut = array_merge($subOut, $this->dataFieldToArray($grandchild));
	    		}
	    		$out[$dataFieldType->getJsonName($dataField->getFieldType())][] = $subOut;
	    	}
	    	else if (! $child->getFieldType()->getDeleted ()) {
	    		if( $dataFieldType->isNested() ){
					$out[$dataFieldType->getJsonName($dataField->getFieldType())] = array_merge($out[$dataFieldType->getJsonName($dataField->getFieldType())], $this->dataFieldToArray($child));
	    		}
// 	    		else if(isset($jsonName)){
// 	    			$out[$jsonName] = array_merge($out[$jsonName], $this->dataFieldToArray($child));
// 	    		}
	    		else{
	    			$out = array_merge($out, $this->dataFieldToArray($child));
	    		}
	    	}
    	}
    	return $out;
    }
    
    public function generateMapping(FieldType $fieldType, $withPipeline = false) {
    	$type = $fieldType->getType();
    	/** @var DataFieldType $dataFieldType */
    	$dataFieldType = new $type();
    	
    	$out = $dataFieldType->generateMapping($fieldType, $withPipeline);
    	
    	$jsonName = $dataFieldType->getJsonName($fieldType);
    	/** @var FieldType $child */
    	foreach ( $fieldType->getChildren () as $child ) {
	    	if (! $child->getDeleted ()) {
	    		if(isset($jsonName)){
	    			if(isset($out[$jsonName]["properties"])){
		    			$out[$jsonName]["properties"] = array_merge($out[$jsonName]["properties"], $this->generateMapping($child, $withPipeline));
	    			}
	    			else{
		    			$out[$jsonName] = array_merge($out[$jsonName], $this->generateMapping($child, $withPipeline));	    				
	    			}
	    		}
	    		else{
		    		$out = array_merge($out, $this->generateMapping($child, $withPipeline));	    			
	    		}
	    	}
    	}
    	return $out;
    }
    
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getBlockPrefix() {
		return 'fieldTypeType';
	}	
	
}
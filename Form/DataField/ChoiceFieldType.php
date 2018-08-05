<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use function is_integer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use EMS\CoreBundle\Entity\DataField;

class ChoiceFieldType extends DataFieldType {


    private $fakeIndex = false;

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getLabel(){
		return 'Choice field';
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function getIcon(){
		return 'glyphicon glyphicon-check';
	}


	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public static function buildObjectArray(DataField $data, array &$out) {
		if (! $data->getFieldType ()->getDeleted ()) {
			if($data->getFieldType()->getDisplayOptions()['multiple']){
				$out [$data->getFieldType ()->getName ()] = $data->getArrayTextValue();
			}
			else{
				parent::buildObjectArray($data, $out);
			}
				
		}
	}

	public function choiceAttr($choiceValue, $key, $value){
        $out = [];
        if($this->fakeIndex !== false && is_int($choiceValue) && $choiceValue >= $this->fakeIndex)
        {
            $out['class'] = 'input-to-hide';
        }
	    return $out;
    }
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {
		
		/** @var FieldType $fieldType */
		$fieldType = $builder->getOptions () ['metadata'];
		
		$choices = [];
		$values = explode("\n", str_replace("\r", "", $options['choices']));
		$labels = explode("\n", str_replace("\r", "", $options['labels']));
		
		foreach ($values as $id => $value){
		    if($value != '')
            {
                if(isset($labels[$id]) && !empty($labels[$id])){
                    $choices[$labels[$id]] = $value;
                }
                else {
                    $choices[$value] = $value;
                }
            }
		}

        if($options['linked_collection'])
        {
            $idx = 0;
            if(isset($options['raw_data'][$options['linked_collection']]) && is_array($options['raw_data'][$options['linked_collection']]))
            {
                foreach ($options['raw_data'][$options['linked_collection']] as $idx => $child)
                {
                    $choices['#'.$idx.': '.((isset($child[$options['collection_label_field']]) && $child[$options['collection_label_field']] !== null)?$child[$options['collection_label_field']]:'')] = $idx;
                }
                ++$idx;
            }

            $this->fakeIndex = $idx;

            for($i=0; $i < 50; ++$i)
            {
                $choices['[ems_hide_input]'.($idx+$i)] = $idx+$i;
            }


        }
		
		$builder->add ( 'value', ChoiceType::class, [ 
				'label' => (isset($options['label'])?$options['label']:$fieldType->getName()),
				'required' => false,
				'disabled'=> $this->isDisabled($options),
				'choices' => $choices,
    			'empty_data'  => null,
				'multiple' => $options['multiple'],
				'expanded' => $options['expanded'],
                'choice_attr' => array($this, 'choiceAttr'),
		] );
	}


	public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars ['attr'] = [
            'data-linked-collection' => $options['linked_collection'],
            'data-collection-label-field' => $options['collection_label_field'],
            'data-multiple' => $options['multiple'],
            'data-expanded' => $options['expanded'],
            'class' => 'ems-choice-field-type',
        ];
    }
	

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function configureOptions(OptionsResolver $resolver) {
		/* set the default option value for this kind of compound field */
		parent::configureOptions ( $resolver );
		$resolver->setDefault ( 'choices', [] );
		$resolver->setDefault ( 'labels', [] );
        $resolver->setDefault ( 'multiple', false );
        $resolver->setDefault ( 'expanded', false );
        $resolver->setDefault ( 'linked_collection', false );
        $resolver->setDefault ( 'collection_label_field', false );

	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
		parent::buildOptionsForm ( $builder, $options );
		$optionsForm = $builder->get ( 'options' );
		
		// String specific display options
		$optionsForm->get ( 'displayOptions' )->add ( 'multiple', CheckboxType::class, [ 
				'required' => false,
		] )->add ( 'expanded', CheckboxType::class, [ 
				'required' => false,
		] )->add ( 'choices', TextareaType::class, [ 
				'required' => false,
		] )->add ( 'labels', TextareaType::class, [ 
				'required' => false,
        ] )->add ( 'linked_collection', TextType::class, [
            'required' => false,
        ] )->add ( 'collection_label_field', TextType::class, [
            'required' => false,
		] );
		
		// String specific mapping options
		$optionsForm->get ( 'mappingOptions' )->add ( 'analyzer', AnalyzerPickerType::class);
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 */
	public function getDefaultOptions($name) {
		$out = parent::getDefaultOptions($name);
		
		$out['mappingOptions']['index'] = 'not_analyzed';
	
		return $out;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::getBlockPrefix()
	 */
	public function getBlockPrefix() {
		return 'ems_choice';
	}
	
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::reverseViewTransform()
	 */
	public function reverseViewTransform($data, FieldType $fieldType) {
		$value = null;
		if(isset($data['value'])){
			$value = $data['value'];
		}
		$out = parent::reverseViewTransform($value, $fieldType);
		return $out;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
	 */
	public function viewTransform(DataField $dataField){
		$temp = parent::viewTransform($dataField);
		$out = [];
		if($dataField->getFieldType()->getDisplayOptions()['multiple']){
			if(empty($temp)){
				$out = [];
			}
			elseif(is_string($temp)) {
				$out = [$temp];
			}
			elseif (is_array($temp) ) {
				$out = [];
				foreach ($temp as $item){
					if(is_string($item) || is_integer($item)){
						$out[] = $item;
					}
					else {
						$dataField->addMessage('Was not able to import the data : '.json_encode($item));
					}
				}
			}
			else {
				$dataField->addMessage('Was not able to import the data : '.json_encode($out));
				$out = [];
			}
		}
		else { //not mutiple
			if(null === $temp) {
				$out = null;
			}
			elseif(is_string($temp) || is_integer($temp)) {
				$out = $temp;
			}
			elseif ( is_array($temp) && $temp != null && (is_string(array_shift($temp)) || is_integer(array_shift($temp)))) {
				$out = array_shift($temp);
				$dataField->addMessage('Only the first item has been imported : '.json_encode($temp));
			}
			else {
				$dataField->addMessage('Was not able to import the data : '.json_encode($temp));
				$out = "";
			}
		}
		
		return [ 'value' => $out ];
		
		
	}
}
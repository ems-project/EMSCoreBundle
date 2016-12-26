<?php 
namespace Ems\CoreBundle\Form\Field;

use Ems\CoreBundle\Entity\ContentType;

class ObjectChoiceListItem {

	private $label;
	private $value;
	private $group;
	private $color;
	
	
	public function __construct(array &$object, ContentType $contentType){
		$this->value = $object['_type'].':'.$object['_id'];
		

		$this->group = null;
		$this->color = null;
		if( null !== $contentType && $contentType->getCategoryField() && isset($object['_source'][$contentType->getCategoryField()] )) {
			$this->group = $object['_source'][$contentType->getCategoryField()];
		}
		
		$this->label = '<i class="fa fa-question"></i> '.$this->value;
		if( null !== $contentType ) {
			$this->label = '<i class="'.(null !== $contentType->getIcon()?$contentType->getIcon():'fa fa-question').'"></i> ';
			if(null !== $contentType->getLabelField() && isset($object['_source'][$contentType->getLabelField()])){
				$this->label .= $object['_source'][$contentType->getLabelField()];
			}
			else {
				$this->label .= $this->value;				
			}
			

			if(null !== $contentType->getColorField() && isset($object['_source'][$contentType->getColorField()])){
				$this->color = $object['_source'][$contentType->getColorField()];
			}
		}
		
	}	
	
	
	
	public function getValue(){
		return $this->value;
	}
	
	public function getLabel(){
		return $this->label;
	}	
	
	public function getGroup(){
		return $this->group;
	}	
	
	public function getColor(){
		return $this->color;
	}	

	public function __toString()
	{
		return $this->getValue();
	}
}
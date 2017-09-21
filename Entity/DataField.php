<?php

namespace EMS\CoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use EMS\CoreBundle\Exception\DataFormatException;
use EMS\CoreBundle\Form\DataField\CollectionFieldType;
use EMS\CoreBundle\Form\DataField\OuuidFieldType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DataField
 *
 * @Assert\Callback({"Vendor\Package\Validator", "validate"})
 */
class DataField implements \ArrayAccess, \IteratorAggregate
{

    /**
     * link to the linked FieldType
     */
    private $fieldType;
    
    
    /**
     * a retirer???
     */
    private $orderKey;

    /**
     * @var FieldType
     */
    private $parent;
    
    /**
     * 
     * @var Collection
     */
    private $children;

    /**
     * object
     */
    private $rawData;

    /**
     * object
     */
    private $inputValue;
    
    
    private $messages;

    
    public function setChildrenFieldType(FieldType $fieldType){
    	//TODO: test if sub colletion for nested collection
    	/** @var FieldType $subType */
    	$this->children->first();
    	foreach ($fieldType->getChildren() as $subType){
    		if(! $subType->getDeleted() ){
    			$child = $this->children->current();
	    		if($child){
	    			$child->setFieldType($subType);
	    			$child->setOrderKey($subType->getOrderKey());
	    			$child->setChildrenFieldType($subType);		
	    		}  		
	    		$this->children->next();
    		}
    	}
    }
    
    /**
     * @deprecated
     * @param DataField $child
     * @param unknown $offset
     * @throws \Exception
     */
    private function initChild(DataField $child, $offset){
    	
    	throw new \Exception('deprecate');
    }
    
    public function offsetSet($offset, $value) {
    	$this->initChild($value, $offset);
    	/** @var DataField $value */
    	return $this->children->offsetSet($offset, $value);
    }
    
    public function offsetExists($offset) {
		if((is_int($offset) || ctype_digit($offset)) && !$this->children->offsetExists($offset) && $this->fieldType !== null && $this->fieldType->getChildren()->count() > 0){
			$value = new DataField();
	    	$this->initChild($value, $offset);
	    	$this->children->offsetSet($offset, $value);
	    	return true;
		}
    	return $this->children->offsetExists($offset);
    }
    
    public function offsetUnset($offset) {
    	return $this->children->offsetUnset($offset);
    }
    
    public function offsetGet($offset) {
    	$value = $this->children->offsetGet($offset);
    	$this->initChild($value, $offset);
    	
    	return $value;
    }   
    
    

    public function getIterator() {
    	return $this->children->getIterator();
    }
    
    
    /**
     * @Assert\Callback
     */
    public function isDataFieldValid(ExecutionContextInterface $context)
    {
    	//TODO: why is it not working?
    	$context->addViolationAt('textValue', 'Haaaaha', array(), null);
    }
    
    public function propagateOuuid($ouuid) {
    	if( $this->getFieldType()  && strcmp(OuuidFieldType::class, $this->getFieldType()->getType()) == 0 ) {
    		$this->setTextValue($ouuid);
    	}
    	foreach ($this->children as $child){
    		$child->propagateOuuid($ouuid);
    	}
    }
    
    
    public function __toString()
    {
    	if( null!== $this->rawData && is_string($this->rawData)){
    		return $this->rawData;
    	}
    	return json_encode($this->rawData);
    }

    public function orderChildren(){
    	$children = null;
    	
    	if($this->getFieldType() == null){
    		$children = $this->getParent()->getFieldType()->getChildren();
    	}
    	else if(strcmp($this->getFieldType()->getType(), CollectionFieldType::class) != 0){
    		$children = $this->getFieldType()->getChildren() ;
    	}
    	
    	
    	if($children){
	    	$temp = new \Doctrine\Common\Collections\ArrayCollection();
	    	/** @var FieldType $childField */
	    	foreach ($children as $childField){
	    		if(!$childField->getDeleted()){    			
		    		$value = $this->__get('ems_'.$childField->getName());
		    		if($value){
			    		$value->setOrderKey($childField->getOrderKey());
			    		if(isset($value)){
				    		$temp->add($value);
			    		}		    			
		    		}
	    		}
	    	}    		
	    	$this->children = $temp;
    	}

    	foreach ($this->children as $child){
    		$child->orderChildren();
    	}
    }
    

    
    
    /**
     * Constructor
     */
    public function __construct()
    {
    	$this->children = new \Doctrine\Common\Collections\ArrayCollection();
    	$this->messages = [];
    	
    	//TODO: should use the clone method
    	$a = func_get_args();
    	$i = func_num_args();
    	if($i >= 1 && $a[0] instanceof DataField){
    		/** @var \DataField $ancestor */
    		$ancestor = $a[0];
    		$this->fieldType = $ancestor->getFieldType();
    		$this->orderKey = $ancestor->orderKey;
    		$this->rawData = $ancestor->rawData;
    		if($i >= 2 && $a[1] instanceof DataField){
    			$this->parent = $a[1];
    		}
    
    		foreach ($ancestor->getChildren() as $child){
    			$this->addChild(new DataField($child, $this));
    		}
    	}/**/
    }
    
    public function __set($key, $input){
    	if(strpos($key, 'ems_') !== 0){
     		throw new \Exception('unprotected ems set with key '.$key);
    	}
    	else{
    		$key = substr($key, 4);
    	}
    	
    	
    	
    	if($input === null || $input instanceof DataField){
	    	$found = false;
	    	if($input !== null){
		    	/** @var DataField $input */
		    	$input->setParent($this);   		
	    	}
	    	
	    	if(null === $this->getFieldType()){
	    		if(NULL === $this->getParent()){
	    			throw new \Exception('null parent !!!!!! '.$key);
	    		}
	    		else{
		    		$this->updateDataStructure($this->getParent()->getFieldType());	    			
	    		}
	    	}
	    	
	    	/** @var DataField $dataField */
	    	foreach ($this->children as &$dataField){
	    		if(null != $dataField->getFieldType() && !$dataField->getFieldType()->getDeleted() && strcmp($key,  $dataField->getFieldType()->getName()) == 0){
	    			$found = true;
	    			$dataField = $input;
	    			break;
	    		}
	    	}
	    	if(! $found){    
	    		throw new \Exception('__set an unknow kind of field '.$key);
	    	}    		
    	}
    	else {
    		throw new \Exception('__set a DataField wich is not a valid object'.$key);
    	}
	    	
    	return $this;
    }
    
    public function updateDataStructure(FieldType $meta){
    	throw new \Exception('Deprecated method');
//     	//no need to generate the structure for subfields (
//     	$isContainer = true;
    	
//     	if(null !== $this->getFieldType()){
// 	    	$type = $this->getFieldType()->getType();
// 	    	$datFieldType = new $type;    	
// 	    	$isContainer = $datFieldType->isContainer();
//     	}
    	
//     	if($isContainer){
//     		/** @var FieldType $field */
//     		foreach ($meta->getChildren() as $field){
//     			//no need to generate the structure for delete field
//     			if(!$field->getDeleted()){
//     				$child = $this->__get('ems_'.$field->getName());
//     				if(null == $child){
//     					$child = new DataField();
//     					$child->setFieldType($field);
//     					$child->setOrderKey($field->getOrderKey());
//     					$child->setParent($this);
//     					$this->addChild($child);
//     					if(isset($field->getDisplayOptions()['defaultValue'])){
//     						$child->setEncodedText($field->getDisplayOptions()['defaultValue']);
//     					}
//     				}
//     				if( strcmp($field->getType(), CollectionFieldType::class) != 0 ) {
//     					$child->updateDataStructure($field);
//     				}
//     			}
//     		}
//     	}
    }
    
    
    /**
     * Assign data in dataValues based on the elastic index content
     * 
     * @param array $elasticIndexDatas
     * @return $elasticIndexDatas
     */
    public  function updateDataValue(Array &$elasticIndexDatas, $isMigration = false){
    	throw new \Exception('Deprecated method');
//     	$dataFieldTypeClassName = $this->fieldType->getType();
//     	/** @var DataFieldType $dataFieldType */
//     	$dataFieldType = new $dataFieldTypeClassName();
//     	$fieldName = $dataFieldType->getJsonName($this->fieldType);
//     	if(NULL === $fieldName) {//Virtual container
//     		/** @var DataField $child */
// 	    	foreach ($this->children as $child){
// 	    		$child->updateDataValue($elasticIndexDatas, $isMigration);
// 	    	}
//     	} 
//     	else {
//     		if($dataFieldType->isVirtualField($this->getFieldType()->getOptions()))  {
//     			$treatedFields = $dataFieldType->importData($this, $elasticIndexDatas, $isMigration);
//     			foreach($treatedFields as $fieldName){
//     				unset($elasticIndexDatas[$fieldName]);
//     			}
//     		}
//     		else if(array_key_exists($fieldName, $elasticIndexDatas)){
//     			$treatedFields = $dataFieldType->importData($this, $elasticIndexDatas[$fieldName], $isMigration);
//     			foreach($treatedFields as $fieldName){
// 	    			unset($elasticIndexDatas[$fieldName]);    				    				
//     			}
//     		}
    		
//     	}
    }
    
    public function linkFieldType(PersistentCollection $fieldTypes){
    	
    	$index = 0;
    	/** @var FieldType $fieldType */
    	foreach ($fieldTypes as $fieldType){
    		if(!$fieldType->getDeleted()){
    			/** @var DataField $child */
    			$child = $this->children->get($index);
    			$child->setFieldType($fieldType);
    			$child->setParent($this);
	    		$child->linkFieldType($fieldType->getChildren());
	    		++$index;
    		}
    	}
    
    	
    }
    
    /**
     * get a child
     *
     * @return DataField
     */    
     public function __get($key){
     	
     	if(strpos($key, 'ems_') !== 0){
     		throw new \Exception('unprotected ems get with key '.$key);
     	}
     	else{
     		$key = substr($key, 4);
     	}
     	
     	if($this->getFieldType() && strcmp ($this->getFieldType()->getType(), CollectionFieldType::class) == 0){
      		//Symfony wants iterate on children
     		return $this;
     	}
     	else {     		
	    	/** @var DataField $dataField */
	    	foreach ($this->children as $dataField){
	    		if(null != $dataField->getFieldType() && !$dataField->getFieldType()->getDeleted() && strcmp($key,  $dataField->getFieldType()->getName()) == 0){	    			
	    			return $dataField;
	    		}
	    	}
     	}
    	
    	return null;
    }
	
	/**
	 * Set textValue
	 *
	 * @param string $textValue        	
	 *
	 * @return DataField
	 */
	public function setTextValue($rawData) {
    	if($rawData !== null && !is_string($rawData)){
    		throw new DataFormatException('String expected: '.print_r($rawData, true));
    	}
		$this->rawData = $rawData;
		return $this;
	}
	
	/**
	 * Get textValue
	 *
	 * @return string
	 */
	public function getTextValue() {
		if(is_array($this->rawData) && count($this->rawData) === 0){
			return null; //empty array means null/empty
		}
		
    	if($this->rawData !== null && !is_string($this->rawData)){
			if(is_array($this->rawData) && count($this->rawData) == 1 && is_string($this->rawData[0])) {
	    		$this->addMessage('String expected, single string in array instead');
				return $this->rawData[0];
			}
			$this->addMessage('String expected from the DB: '.print_r($this->rawData, true));
    	}
		return $this->rawData;
	}
	
	/**
	 * Set passwordValue
	 *
	 * @param string $passwordValue        	
	 *
	 * @return DataField
	 */
	public function setPasswordValue($passwordValue) {
		if (isset ( $passwordValue )) {
        	$this->setTextValue($passwordValue);
		}
		
		return $this;
	}
	
	/**
	 * Get passwordValue
	 *
	 * @return string
	 */
	public function getPasswordValue() {
		return $this->getTextValue();
	}
	
	/**
	 * Set resetPasswordValue
	 *
	 * @param string $resetPasswordValue        	
	 *
	 * @return DataField
	 */
	public function setResetPasswordValue($resetPasswordValue) {
		if (isset ( $resetPasswordValue ) && $resetPasswordValue) {
			$this->setTextValue(null);
		}
		
		return $this;
	}
	
	/**
	 * Get resetPasswordValue
	 *
	 * @return string
	 */
	public function getResetPasswordValue() {
		return false;
	}
	
	
	
	/**
	 * Set floatValue
	 *
	 * @param float $floatValue        	
	 *
	 * @return DataField
	 */
	public function setFloatValue($rawData) {
    	if($rawData !== null && !is_finite($rawData)){
    		throw new DataFormatException('Float or double expected: '.print_r($rawData, true));
    	}
		$this->rawData = $rawData;
		return $this;
	}
	
	/**
	 * Get floatValue
	 *
	 * @return float
	 */
	public function getFloatValue() {
		if(is_array($this->rawData) && count($this->rawData) === 0){
			return null; //empty array means null/empty
		}
		
    	if($this->rawData !== null && !is_finite($this->rawData)){
    		throw new DataFormatException('Float or double expected: '.print_r($this->rawData, true));
    	}
		return $this->rawData;
	}
	
	


	/**
	 * Set dataValue, the set of field is delegated to the corresponding fieldType class
	 *
	 * @param \DateTime $dateValue
	 *
	 * @return DataField
	 */
	public function setDataValue($inputString) {
		$this->getFieldType()->setDataValue($inputString, $this);		
		return $this;
	}
	


	public function getMessages() {
		return $this->messages;
	}

	public function addMessage($message) {
		if(!in_array($message, $this->messages)){
			$this->messages[] = $message;
		}
	}

	/**
	 * Get dataValue, the get of field is delegated to the corresponding fieldType class
	 *
	 * @param \DateTime $dateValue
	 * @deprecated
	 *
	 * @return DataField
	 */
	public function getDataValue() {
		throw new Exception('should never came here');
		if($this->getFieldType())
			return $this->getFieldType()->getDataValue($this);
		return null;
	}
	
	/**
	 * Set arrayTextValue
	 *
	 * @param array $arrayValue        	
	 *
	 * @return DataField
	 */
	public function setArrayTextValue($rawData) {
		if($rawData === null){
			$this->rawData = null;
		}
    	else if( !is_array($rawData)){
    		throw new DataFormatException('Array expected: '.print_r($rawData, true));
    	}
    	else {
	    	foreach ($rawData as $item){
	    		if(!is_string($item)){
	    			throw new DataFormatException('String expected: '.print_r($item, true));
	    		}
	    	}
			$this->rawData = $rawData;    		
    	}
		return $this;
	}
	
	/**
	 * Get arrayValue
	 *
	 * @return string
	 */
	public function getArrayTextValue() {
    	$out = $this->rawData;
    	if($out !== null){
    		if(!is_array($out)){
    			$this->addMessage('Array expected from the DB: '.print_r($this->rawData, true));
    			return null;
    		}
	    	foreach ($out as $idx => $item){
	    		if(!is_string($item)){
	    			$this->addMessage('String expected for the item '.$idx.' from the DB: '.print_r($this->rawData, true));
	    			$out[$idx] = "";
	    		}
	    	}
    	}
		return $out;
	}
	
	/**
	 * Get integerValue
	 *
	 * @return integer
	 */
	public function getIntegerValue() {
		if(is_array($this->rawData)){
			$this->addMessage('Integer expected array found: '.print_r($this->rawData, true));
			return count($this->rawData); //empty array means null/empty
		}
		
    	if($this->rawData === null || is_int($this->rawData)){
			return $this->rawData;
		}
    	else if( intval($this->rawData) || $this->rawData === 0 || $this->rawData === '0'){

    		return intval($this->rawData);
//     		return $this->rawData;
//     		throw new DataFormatException('Integer expected: '.print_r($this->rawData, true));
    	}
    	$this->addMessage('Integer expected: '.print_r($this->rawData, true));
		return $this->rawData;
	}
	
	/**
	 * Set integerValue
	 *
	 * @param integer $integerValue        	
	 *
	 * @return DataField
	 */
	public function setIntegerValue($rawData) {
		if($rawData === null || is_int($rawData)){
			$this->rawData = $rawData;
		}
    	else if(intval($rawData) || $rawData === 0 || $rawData === '0'){
			$this->rawData = intval($rawData);
    	}
    	else{
    		$this->addMessage('Integer expected: '.print_r($rawData, true));
			$this->rawData = $rawData;    		
    	}
    	
		return $this;
	}    
	
	/**
	 * Get booleanValue
	 *
	 * @return boolean
	 */
	public function getBooleanValue() {
		if(is_array($this->rawData) && count($this->rawData) === 0){
			return null; //empty array means null/empty
		}
		
    	if($this->rawData !== null && !is_bool($this->rawData)){
    		throw new DataFormatException('Boolean expected: '.print_r($this->rawData, true));
    	}
		return $this->rawData;
	}    

	public function getDateValues() {
		$out = [];
		if($this->rawData !== null){
			if(!is_array($this->rawData)){
				throw new DataFormatException('Array expected: '.print_r($this->rawData, true));
			}
			foreach ($this->rawData as $item){
				/**@var \DateTime $converted*/
				$out[] = \DateTime::createFromFormat(\DateTime::ISO8601, $item);
			}
		}
		return $out;
	}
	
    /**
     * Set booleanValue
     *
     * @param boolean $booleanValue
     *
     * @return DataField
     */
    public function setBooleanValue($rawData)
    {
    	if($rawData !== null && !is_bool($rawData)){
    		throw new DataFormatException('Boolean expected: '.$rawData);
    	}
    	$this->rawData = $rawData;

        return $this;
    }
	
    /**
     * 
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function getRootDataField() {
    	$out = $this;
    	while($out->getParent()){
    		$out  = $out->getParent();
    	}
    	return $out;
    }
    
    public function setMarked($marked)
    {
    	$this->marked = $marked;
    
    	return $this;
    }
    
    
    public function isMarked()
    {
    	return $this->marked;
    }
    
	
    /****************************
     * Generated methods
     ****************************
     */


    /**
     * JSON decode the input string and save it has array in rawdata
     *
     * @param string $text
     *
     * @return DataField
     */
    public function setEncodedText($text)
    {
    	$this->rawData = json_decode($text, true);
    	return $this;
    }
    
    /**
     * Get the rawdata in a text encoded form
     *
     * @return string
     */
    public function getEncodedText()
    {
    	return json_encode($this->rawData);
    }

    /**
     * Set orderKey
     *
     * @param integer $orderKey
     *
     * @return DataField
     */
    public function setOrderKey($orderKey)
    {
    	$this->orderKey = $orderKey;
    
    	return $this;
    }
    
    /**
     * Get orderKey
     *
     * @return integer
     */
    public function getOrderKey()
    {
    	return $this->orderKey;
    }
    /**
     * Set fieldType
     *
     * @param \EMS\CoreBundle\Entity\FieldType $fieldType
     *
     * @return DataField
     */
    public function setFieldType(\EMS\CoreBundle\Entity\FieldType $fieldType = null)
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    /**
     * Get fieldType
     *
     * @return \EMS\CoreBundle\Entity\FieldType
     */
    public function getFieldType()
    {
        return $this->fieldType;
    }

    /**
     * Set parent
     *
     * @param \EMS\CoreBundle\Entity\DataField $parent
     *
     * @return DataField
     */
    public function setParent(\EMS\CoreBundle\Entity\DataField $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add child
     *
     * @param \EMS\CoreBundle\Entity\DataField $child
     *
     * @return DataField
     */
    public function addChild(\EMS\CoreBundle\Entity\DataField $child)
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Remove child
     *
     * @param \EMS\CoreBundle\Entity\DataField $child
     */
    public function removeChild(\EMS\CoreBundle\Entity\DataField $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * Get children
     *
     * @return Collection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set rawData
     *
     * @param array $rawData
     *
     * @return DataField
     */
    public function setRawData($rawData)
    {
        $this->rawData = $rawData;

        return $this;
    }

    /**
     * Get rawData
     *
     * @return array
     */
    public function getRawData()
    {
        return $this->rawData;
    }

    /**
     * Set rawData
     *
     * @param array $rawData
     *
     * @return DataField
     */
    public function setInputValue($inputValue)
    {
        $this->inputValue = $inputValue;

        return $this;
    }

    /**
     * Get rawData
     *
     * @return array
     */
    public function getInputValue()
    {
        return $this->inputValue;
    }
}

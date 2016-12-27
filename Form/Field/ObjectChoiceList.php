<?php 
namespace EMS\CoreBundle\Form\Field;


use EMS\CoreBundle\Service\ObjectChoiceCacheService;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;

class ObjectChoiceList implements ChoiceListInterface {
	/**@var ObjectChoiceCacheService $objectChoiceCacheService*/
	private $objectChoiceCacheService;

	private $types;
	private $choices;
	private $loadAll;
	
	public function __construct(
			ObjectChoiceCacheService $objectChoiceCacheService,
			$types = false,
			$loadAll = false){
		
		$this->objectChoiceCacheService = $objectChoiceCacheService;
		$this->choices = [];		
		$this->types = $types;
		$this->loadAll = $loadAll;
		
	}
	
	public function getTypes(){
		return $this->types;
	}
	
	/**
     * {@inheritdoc}
     */
    public function getChoices(){
	    $this->loadAll($this->types);  
		return $this->choices;
	}
	
	/**
     * {@inheritdoc}
     */
    public function getValues(){
		return array_keys($this->choices);
	}
	
	/**
     * {@inheritdoc}
     */
    public function getStructuredValues(){
		$out = [[]];
		foreach ($this->choices as $key => $choice){
			$out[0][$key] = $key;
		}
		return $out;
	}
	
	/**
     * {@inheritdoc}
     */
    public function getOriginalKeys(){
		return $this->choices;
	}
	
	/**
     * {@inheritdoc}
     */
    public function getChoicesForValues(array $choices){
		$this->choices = $this->objectChoiceCacheService->load($choices);
		return array_keys($this->choices);
	}
	
	/**
     * {@inheritdoc}
     */
    public function getValuesForChoices(array $choices){
		$this->choices = $this->objectChoiceCacheService->load($choices);
		return array_keys($this->choices);
	}
	
	public function loadAll($types){
		if($this->loadAll){
			$this->choices = $this->objectChoiceCacheService->loadAll($types);
		}
		return $this->choices;
	}
	
	/**
	 * intiate (or re-initiate) the choices array based on the list of key passed in parameter
	 * 
	 * @param array $choices
	 */
	public function loadChoices(array $choices){
		$this->choices = $this->objectChoiceCacheService->load($choices);
		return $this->choices;
	}
}
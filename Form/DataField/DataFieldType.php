<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Exception\ContentTypeStructureException;
use EMS\CoreBundle\Form\DataField\Options\OptionsType;
use EMS\CoreBundle\Service\ElasticsearchService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Form\Field\SelectPickerType;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


/**
 * It's the mother class of all specific DataField used in eMS
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 *        
 */
abstract class DataFieldType extends AbstractType {
    
    /**@var AuthorizationCheckerInterface $authorizationChecker*/
    protected $authorizationChecker;
    /**@var FormRegistryInterface $formRegistry*/
    protected $formRegistry;
    /**@var ElasticsearchService*/
    protected $elasticsearchService;


    public function __construct(AuthorizationCheckerInterface $authorizationChecker, FormRegistryInterface $formRegistry, ElasticsearchService $elasticsearchService) {
        $this->authorizationChecker = $authorizationChecker;
        $this->formRegistry = $formRegistry;
        $this->elasticsearchService = $elasticsearchService;
    }
    
    public function getBlockPrefix() {
        return 'data_field_type';
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDisabled($this->isDisabled($options));
    }
    
    /**
     * Perfom field specfifc post-finalized treatment. It returns the children if it's a container
     * 
     * @param string $type
     * @param string $id
     * @param DataField $dataField
     * @param array $previousData
     * @return array|null
     */
    public function postFinalizeTreatment($type, $id, DataField $dataField, $previousData) {
        return $previousData;
    }
    
    
    /**
     * form array to DataField 
     * 
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     * 
     * @param unknown $data
     * @param FieldType $fieldType
     * @return \EMS\CoreBundle\Entity\DataField
     */
    public function reverseViewTransform($data, FieldType $fieldType) {
        $out = new DataField();
        
        if((is_string($data) && $data === "") || (is_array($data) && count($data) === 0)) {
            $out->setRawData(null);    
        }
        else {
            $out->setRawData($data);            
        }
        $out->setFieldType($fieldType);
        return $out;
    }
    
    
    /**
     * datafield to form array : 
     * 
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     * 
     * @param DataField $data
     * @return array|null|string|integer|float
     */
    public function viewTransform(DataField $dataField) {
        return $dataField->getRawData();
    }
    
    
    /**
     * datafield to raw_data array : 
     * 
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     * 
     * @param DataField $data
     * 
     * @return array|null|string|integer|float
     */
    public function reverseModelTransform(DataField $dataField) {
        return $dataField->getRawData();
    }
    
    
    /**
     * raw_data array to datafield: 
     * 
     * http://symfony.com/doc/current/form/data_transformers.html#about-model-and-view-transformers
     * 
     * @param unknown $data
     * @return DataField
     */
    public function modelTransform($data, FieldType $fieldType) {
        $out = new DataField();
        $out->setRawData($data);
        $out->setFieldType($fieldType);
        return $out;
    }
    
    /**@var FormRegistryInterface*/
    public function getFormRegistry(){
        return $this->formRegistry;
    }
    
    public function convertInput(DataField $dataField){
        //by default do nothing
    }
    
    public function generateInput(DataField $dataField){
        //by default do nothing
    }
    
    
    public function getDefaultOptions($name) {
        return [
            'displayOptions' => [
                'label' => SelectPickerType::humanize($name),
                'class' => 'col-md-12',
            ],
            'mappingOptions' => [
            ],
            'restrictionOptions' => [
            ],
            'extraOptions' => [
            ],
            'raw_data' => [
            ],
        ];
    }

    /**
     * Used to display in the content type edit page (instaed of the class path)
     * 
     * @return string
     */
    abstract public function getLabel();    

    public function isDisabled($options){
        if(strcmp('cli', php_sapi_name()) === 0){
            return false;
        }

        /** @var FieldType $fieldType */
        $fieldType = $options ['metadata'];
        
        $enable = ($options['migration'] && !$fieldType->getMigrationgOption('protected', true)) || $this->authorizationChecker->isGranted($fieldType->getMinimumRole());
        return !$enable;
    }
    

    /**
     * Get Elasticsearch subquery
     *
     * @return array
     */
    public function getElasticsearchQuery(DataField $dataField, array $options = [])
    {
        throw new \Exception('virtual method should be implemented by child class : '.get_class($this));
    }
    
    /**
     * get the data value(s), as string, for the symfony form) in the context of this field
     *
     */
    public function getDataValue(DataField &$dataValues, array $options){
        //TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }    
    /**
     * set the data value(s) from a string recieved from the symfony form) in the context of this field
     *
     */
    public function setDataValue($input, DataField &$dataValues, array $options){
        //TODO: should be abstract ??
        throw new \Exception('This function should never be called');
    }
    
    /**
     * get the list of all possible values (if it means something) filter by the values array if not empty
     *
     */
    public function getChoiceList(FieldType $fieldType, array $choices){
        //TODO: should be abstract ??
        throw new ContentTypeStructureException('The field '.$fieldType->getName().' of the content type '.$fieldType->getContentType()->getName().' does not have a limited list of values!');
    }
    
    /**
     * Get a icon to visually identify a FieldType
     * 
     * @return string
     */
    public static function getIcon(){
        return 'fa fa-square';
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults ( [ 
                //'data_class' => 'EMS\CoreBundle\Entity\DataField',
                'lastOfRow' => false,
                'class' => null, // used to specify a bootstrap class arround the compoment
                'metadata' => null, // used to keep a link to the FieldType
                'error_bubbling' => false,
                'required' => false,
                'translation_domain' => false,
                'migration' => false,
                'raw_data' => [],
                'helptext' => null,
        ]);
    }
    
    /**
     * See if we can asume that we should find this field directly or if its a more complex type such as file or date range
     * @deprecated
     * @param array $option
     * @return boolean
     */
    public static function isVirtualField(array $option){
        return false;
    }
    
    /**
     * Assign data of the dataField based on the elastic index content ($sourceArray)
     * 
     * @param DataField $dataField
     * @param unknown $sourceArray
     */
    public function importData(DataField $dataField, $sourceArray, $isMigration) {
        $migrationOptions = $dataField->getFieldType()->getMigrationOptions();
        if(!$isMigration || empty($migrationOptions) || !$migrationOptions['protected']) {
            $dataField->setRawData($sourceArray);            
        }
        return [$dataField->getFieldType()->getName()];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Symfony\Component\Form\AbstractType::buildView()
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars ['class'] = $options ['class'];
        $view->vars ['lastOfRow'] = $options ['lastOfRow'];
        $view->vars ['helptext'] = $options ['helptext'];
        $view->vars ['isContainer'] = $this->isContainer();
        if( null == $options['label']){
//             /** @var FieldType $fieldType */
//             $fieldType = $options ['metadata'];
            $view->vars ['label'] = false;//$fieldType->getName();
        }
        if($form->getErrors()->count() > 0 && !$form->getConfig()->getType()->getInnerType()->isContainer() && $form->has('value')) {
            foreach ($form->getErrors() as $error) {
                $form->get('value')->addError($error);
            }
        }
    }
    
    
    /**
     * Build an array representing the object, this array is ready to be serialized in json
     * and push in elasticsearch
     *
     * @return array
     */
    public static function buildObjectArray(DataField $data, array &$out) {
        if (! $data->getFieldType ()->getDeleted ()) {
            /**
             * by default it serialize the text value.
             * It can be overrided.
             */
            $out [$data->getFieldType ()->getName ()] = $data->getTextValue ();
        }
    }
    
    /**
     * Test if the field may contain sub field.
     *
     * I.e. container, nested, array, ...
     *
     * @return boolean
     */
    public static function isContainer() {
        return false;
    }
    
    public static function isNested(){
        return false;
    }
    
    public static function isCollection(){
        return false;
    }
    
    /**
     * Test if the field is valid.
     *
     * @return boolean
     */
    public function isValid(DataField &$dataField, DataField $parent=null, &$masterRawData=null){
        if($this->hasDeletedParent($parent))
        {
            return true;
        }

        return count($dataField->getMessages()) === 0 && $this->isMandatory($dataField, $parent, $masterRawData);
    }
    
    /**
     * Test if the requirment of the field is reached.
     *
     * @return boolean
     */
    public function isMandatory(DataField &$dataField, DataField $parent=null, &$masterRawData=null){
        $isValidMadatory = TRUE;
        //Get FieldType mandatory option
        $restrictionOptions = $dataField->getFieldType()->getRestrictionOptions();
        if(isset($restrictionOptions["mandatory"]) && true == $restrictionOptions["mandatory"]) {

            if($parent === null || !isset($restrictionOptions["mandatory_if"]) || $parent->getRawData() === null || (is_array($masterRawData) && !empty($this->resolve($masterRawData, $parent->getRawData(), $restrictionOptions["mandatory_if"])) )) {

                //Get rawData
                $rawData = $dataField->getRawData();
                if( !isset($rawData) || (is_string($rawData) && $rawData=== "") || (is_array($rawData) && count($rawData) === 0) || $rawData === null ) {
                    $isValidMadatory = FALSE;
                    $dataField->addMessage("Empty field");
                }
            }
        }
        return $isValidMadatory;
    }

    public static function resolve(array $rawData, array $parentRawData, $path, $default = null)
    {
        $current = $rawData;
        if (strlen($path) && substr($path, 0, 1) === '.') {
            $current = $parentRawData;
        }

        $p = strtok($path, '.');
        while ($p !== false) {
            if (!isset($current[$p])) {
                return $default;
            }
            $current = $current[$p];
            $p = strtok('.');
        }

        return $current;
    }

    public function hasDeletedParent(DataField $parent=null)
    {
        if(!$parent)
        {
            return false;
        }
        if(isset($parent->getRawData()['_ems_internal_deleted']) && $parent->getRawData()['_ems_internal_deleted'] == 'deleted')
        {
            return true;
        }
        return $parent->getParent()?$this->hasDeletedParent($parent->getParent()):false;
    }

    /**
     * Build a Field specific options sub-form (or compount field) (used in edit content type).
     *
     * @param FormBuilderInterface $builder            
     * @param array $options            
     */
    public function buildOptionsForm(FormBuilderInterface $builder, array $options) {
        /**
         * preset with the most used options
         */
        $builder->add ( 'options', OptionsType::class, [
        ] );
    }
    
    /**
     * return true if the field exist as is in elasticsearch
     * 
     * @return boolean
     */
    public static function isVirtual(array $option=[]){
        return false;
    }
    
    /**
     * return an array filtered with subfields foir this specfic fieldtype (in case of virtualfield wich is not a container (datarange)) 
     *
     * @return boolean
     */
    public static function filterSubField(array $data, array $option){
        throw new \Exception('Only a non-container datafield which is virtual (i.e. a non-nested datarange) can be filtered');
    }

    /**
     * Return the json path
     * 
     * @param FieldType $current
     * @return string
     */
    public static function getJsonName(FieldType $current){
        return $current->getName();
    }
    
    /**
     * Build an elasticsearch mapping options as an array
     * 
     * @param array $options
     * @param FieldType $current
     */
    public function generateMapping(FieldType $current, $withPipeline){
        $options = $this->elasticsearchService->updateMapping(array_merge(["type" => "string"],  array_filter($current->getMappingOptions())));

        return [ $current->getName() => $options ];
    }


    /**
     * {@inheritdoc}
     */
    public static function generatePipeline(FieldType $current){
        return false;
    }
}
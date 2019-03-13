<?php

namespace EMS\CoreBundle\Form\DataField;




class OuuidFieldType extends DataFieldType {
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getLabel(){
        return 'Copy of the object identifier';
    }    
    
    /**
     * Get a icon to visually identify a FieldType
     * 
     * @return string
     */
    public static function getIcon(){
        return 'fa fa-key';
    }
    
    public function getBlockPrefix() {
        return 'empty';
    }
    

}
<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class SelectPickerType extends ChoiceType {
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getParent() {
        return ChoiceType::class;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function getBlockPrefix() {
        return 'selectpicker';
    }
    

    public static function humanize($str) {
    
        $str = trim(strtolower($str));
        $str = preg_replace('/\_/', ' ', $str);
        $str = preg_replace('/[^a-z0-9\s+\-]/', '', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        $str = preg_replace('/\-/', ' ', $str);
        $str = explode(' ', $str);
    
        $str = array_map('ucwords', $str);
    
        return implode(' ', $str);
    }
}

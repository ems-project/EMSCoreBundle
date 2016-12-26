<?php 

namespace Ems\CoreBundle\Form\DataField\Options;

use Ems\CoreBundle\Entity\FieldType;

use Ems\CoreBundle\Form\DataField\Options\DisplayOptionsType;
use Ems\CoreBundle\Form\DataField\Options\MappingOptionsType;
use Ems\CoreBundle\Form\DataField\Options\RestrictionOptionsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;


/**
 * It's the default option compound field of eMS data type.
 * The panes for display and mapping options are added.
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class OptionsType extends AbstractType
{
	
	/**
	 * {@inheritdoc}
	 */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    	$builder->add ( 'displayOptions',     DisplayOptionsType::class);
	    $builder->add ( 'mappingOptions',     MappingOptionsType::class); 
	    $builder->add ( 'restrictionOptions', RestrictionOptionsType::class); 
	    $builder->add ( 'migrationOptions',	  MigrationOptionsType::class); 
	    $builder->add ( 'extraOptions',	      ExtraOptionsType::class); 
    }   
	
	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix() {
		return 'data_field_options';
	}
	
	public function hasMappingOptions() {
		return false;
	}
	
	public function hasMigrationOptions() {
		return true;
	}
	
	public function hasExtraOptions() {
		return true;
	}
	
	public static function generateMapping(array $options, FieldType $current){
		return [];
	}
}
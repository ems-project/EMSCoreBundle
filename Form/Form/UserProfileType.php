<?php
namespace Ems\CoreBundle\Form\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class UserProfileType extends AbstractType {
	
	/**@var TokenStorageInterface */
	private $tokenStorage;
	
	public function __construct(TokenStorageInterface $tokenStorage) {
		$this->tokenStorage = $tokenStorage;
	}
	
	
	/**
	 *
	 * @param FormBuilderInterface $builder        	
	 * @param array $options        	
	 */
	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder
			->add ('displayName')
			->add ('layoutBoxed')
			->add ('sidebarMini')
			->add ('sidebarCollapse')
			->remove('username');
		
		if($this->tokenStorage->getToken()->getUser()->getAllowedToConfigureWysiwyg()){
			$builder->add('wysiwygProfile', ChoiceType::class, [
						'required' => true,
						'label' => 'WYSIWYG profile',
						'choices' => [
							'Standard' => 'standard',
							'Light' => 'light',
							'Full' => 'full',
							'Custom' => 'custom'
						]
				])
				->add('wysiwygOptions', TextareaType::class, [
						'required' => false,
						'label' => 'WYSIWYG custom options',
						'attr' => [
							'rows' => 8,
						]
				]);			
		}
	}
	
	public function getParent()
	{
		return 'FOS\UserBundle\Form\Type\ProfileFormType';
	}
	
}

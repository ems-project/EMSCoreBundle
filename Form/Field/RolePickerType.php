<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\OptionsResolver\OptionsResolver;
use EMS\CoreBundle\Service\UserService;

class RolePickerType extends SelectPickerType {
	/**
	 * 
	 * @var UserService $userService
	 */
	private $userService;
	
	public function __construct(UserService $userService)
	{
		parent::__construct();
		$this->userService = $userService;
	}

	/**
	 * @param OptionsResolver $resolver
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$choices = $this->getExistingRoles();

		$resolver->setDefaults(array(
			'choices' => $choices,
			'attr' => [
					'data-live-search' => true
			],
			'choice_attr' => function($category, $key, $index) {
				//TODO: it would be nice to translate the roles
				return [
						'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$this->humanize($key).'</div>'
				];
			},
			'choice_value' => function ($value) {
		       return $value;
		    },
		));
	}
	
	private  function getExistingRoles()
	{
		$roleHierarchy = $this->userService->getsecurityRoles();
		$roles = array_keys($roleHierarchy);
		
		$theRoles['not-defined'] = 'not-defined';
		$theRoles['ROLE_USER'] = 'ROLE_USER';
		
		foreach ($roles as $role) {
			$theRoles[$role] = $role;
		}
		$theRoles['ROLE_API'] = 'ROLE_API';
		return $theRoles;
	}
}

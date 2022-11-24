This folder namespace contains SubformType used to generate eMS content types forms. 

They all inherit from the EMS\CoreBundle\Form\DataField\DataFieldType class.

All inputType should have those 2 options required and disabled defined as bellow:

		$builder->add ( 'fieldName', InputType::class, [
				'required' => false,
				'disabled'=> !$this->authorizationChecker->isGranted($fieldType->getMinimumRole()),
		] );	
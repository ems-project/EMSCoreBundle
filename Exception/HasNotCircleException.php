<?php

namespace Ems\CoreBundle\Exception;


use Ems\CoreBundle\Entity\Environment;

class HasNotCircleException extends \Exception
{
	
	private $environment;
	
	public function __construct(Environment $environment) {
		$this->environment = $environment;
		$message = "The User has no circle to manipulate the object in the environment ".$environment->getName();
		parent::__construct($message, 0, null);
	}

	public function getEnvironment() {
		return $this->environment;
	}
	
}

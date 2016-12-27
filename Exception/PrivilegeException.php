<?php

namespace EMS\CoreBundle\Exception;


use EMS\CoreBundle\Entity\Revision;

class PrivilegeException extends \Exception
{
	
	private $revision;
	
	public function __construct(Revision $revision) {
		$this->revision = $revision;
		if($revision->getContentType()){
			$message = "Not enough privilege the manipulate the object ".$revision->getContentType()->getName().":".$revision->getOuuid();			
		}
		else {
			throw new \Exception("Not enough privilege the manipulate the object");
		}
		parent::__construct($message, 0, null);
	}

	public function getRevision() {
		return $this->revision;
	}
	
}

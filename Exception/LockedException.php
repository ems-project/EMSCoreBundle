<?php

namespace EMS\CoreBundle\Exception;


use EMS\CoreBundle\Entity\Revision;

class LockedException extends \Exception
{
	private $revision;
	public function __construct(Revision $revision) {
		$this->revision = $revision;
		$message = "Revision ".$revision->getStartTime()->format("c")." of the object ".$revision->getContentType()->getName().":".$revision->getOuuid()." is locked by ".$revision->getLockBy()." until ".$revision->getLockUntil()->format("c");
		parent::__construct($message, 0, null);
	}
	
	
	public function getRevision() {
		return $this->revision;
	}
	
}

<?php
namespace Ems\CoreBundle\Event;

use Ems\CoreBundle\Entity\Revision;
use Symfony\Component\EventDispatcher\Event;

/**
 */
class RevisionEvent extends Event
{
	protected $revision;

	public function __construct(Revision $revision)
	{
		$this->revision = $revision;
	}

	public function getRevision()
	{
		return $this->revision;
	}
}
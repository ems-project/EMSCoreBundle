<?php
namespace Ems\CoreBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Ems\CoreBundle\Entity\Revision;
use Ems\CoreBundle\Entity\Environment;

/**
 */
class RevisionPublishEvent extends RevisionEvent
{
	const NAME = 'revision.publish';

	protected $environment;

	public function __construct(Revision $revision, Environment $environment)
	{
		parent::__construct($revision);
		$this->environment = $environment;
	}

	public function getEnvironment()
	{
		return $this->environment;
	}
}
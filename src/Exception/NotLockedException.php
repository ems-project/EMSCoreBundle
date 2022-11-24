<?php

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class NotLockedException extends \Exception
{
    private Revision $revision;

    public function __construct(Revision $revision)
    {
        $this->revision = $revision;
        $message = 'Update on a not locked object '.$revision->giveContentType()->getName().':'.$revision->getOuuid().' #'.$revision->getId();
        parent::__construct($message, 0, null);
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    public function __toString()
    {
        return parent::getMessage();
    }
}

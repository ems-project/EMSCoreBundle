<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class NotLockedException extends \Exception
{
    private $revision;

    public function __construct(Revision $revision)
    {
        $this->revision = $revision;
        $message = 'Update on a not locked object '.$revision->getContentType()->getName().':'.$revision->getOuuid().' #'.$revision->getId();
        parent::__construct($message, 0, null);
    }

    public function getRevision()
    {
        return $this->revision;
    }

    public function __toString()
    {
        return parent::getMessage();
    }
}

<?php

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class PrivilegeException extends \Exception
{
    private Revision $revision;

    public function __construct(Revision $revision, string $message = 'Not enough privilege the manipulate the object')
    {
        $this->revision = $revision;
        if ($revision->getContentType()) {
            $message = $message.' '.$revision->giveContentType()->getName().':'.$revision->getOuuid();
        } else {
            throw new \Exception($message);
        }
        parent::__construct($message, 0, null);
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }
}

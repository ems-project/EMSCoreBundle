<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class PrivilegeException extends \Exception
{
    public function __construct(private readonly Revision $revision, string $message = 'Not enough privilege the manipulate the object')
    {
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

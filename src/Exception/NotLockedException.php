<?php

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class NotLockedException extends \Exception implements \Stringable
{
    public function __construct(private readonly Revision $revision)
    {
        $message = 'Update on a not locked object '.$revision->giveContentType()->getName().':'.$revision->getOuuid().' #'.$revision->getId();
        parent::__construct($message, 0, null);
    }

    public function getRevision(): Revision
    {
        return $this->revision;
    }

    #[\Override]
    public function __toString(): string
    {
        return parent::getMessage();
    }
}

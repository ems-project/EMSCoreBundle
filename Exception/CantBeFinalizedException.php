<?php

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class CantBeFinalizedException extends ElasticmsException
{
    public function __construct(string $originMessage = "", $code = 0, \Throwable $previous = null, Revision $revision = null)
    {
        if ($revision === null) {
            $message = "This revision can not be finalized";
        } elseif ($revision->getId() !== null) {
            $message = sprintf('The revision %s of the document %s:%s can not be finalized', $revision->getId(), $revision->getContentType()->getName(), $revision->getOuuid());
        } else {
            $message = sprintf('A new revision for the document %s:%s can not be finalized', $revision->getContentType()->getName(), $revision->getOuuid());
        }
        if ($originMessage !== '') {
            $message .= sprintf(' : %s', $originMessage);
        }
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return parent::getMessage();
    }
}

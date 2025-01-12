<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Revision;

class CantBeFinalizedException extends ElasticmsException implements \Stringable
{
    public function __construct(string $originMessage = '', int $code = 0, ?\Throwable $previous = null, ?Revision $revision = null)
    {
        if (null === $revision) {
            $message = 'This revision can not be finalized';
        } elseif ($revision->hasId()) {
            $message = \sprintf('The revision %s of the document %s:%s can not be finalized', $revision->getId(), $revision->giveContentType()->getName(), $revision->getOuuid());
        } else {
            $message = \sprintf('A new revision for the document %s:%s can not be finalized', $revision->giveContentType()->getName(), $revision->getOuuid());
        }
        if ('' !== $originMessage) {
            $message .= \sprintf(' : %s', $originMessage);
        }
        parent::__construct($message, $code, $previous);
    }

    #[\Override]
    public function __toString(): string
    {
        return parent::getMessage();
    }
}

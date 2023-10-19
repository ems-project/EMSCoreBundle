<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\Xliff\Xliff\InsertionRevision;

class XliffException extends \Exception
{
    public function __construct(private readonly InsertionRevision $insertionRevision, string $message)
    {
        parent::__construct($message);
    }

    public function getInsertionRevision(): InsertionRevision
    {
        return $this->insertionRevision;
    }
}

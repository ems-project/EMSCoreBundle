<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\Xliff\Model\Package;

class XliffException extends \Exception
{
    public function __construct(private readonly Package $package, string $message)
    {
        parent::__construct($message);
    }

    public function getPackage(): Package
    {
        return $this->package;
    }
}

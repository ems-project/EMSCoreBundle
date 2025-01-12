<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CoreBundle\Entity\Environment;

class HasNotCircleException extends ElasticmsException
{
    public function __construct(private readonly Environment $environment)
    {
        $message = 'The User has no circle to manipulate the object in the environment '.$environment->getName();
        parent::__construct($message, 0, null);
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}

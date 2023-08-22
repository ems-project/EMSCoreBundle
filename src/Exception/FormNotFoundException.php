<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FormNotFoundException extends NotFoundHttpException
{
}

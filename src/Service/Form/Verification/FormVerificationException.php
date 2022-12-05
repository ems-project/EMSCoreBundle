<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Verification;

use Symfony\Component\HttpFoundation\Response;

final class FormVerificationException extends \Exception
{
    public function __construct(string $message = '', private readonly int $httpCode = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}

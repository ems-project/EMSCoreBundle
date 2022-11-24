<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Verification;

use Symfony\Component\HttpFoundation\Response;

final class FormVerificationException extends \Exception
{
    private int $httpCode;

    public function __construct(string $message = '', int $httpCode = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}

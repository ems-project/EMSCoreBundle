<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\FormVerification;

final class FormVerificationService
{
    private const CODE = '569876';

    public function generateCode(string $value): string
    {
        return self::CODE;
    }

    public function verify(string $value, string $code): bool
    {
        return $this->getCodyByValue($value) === $code;
    }

    private function getCodyByValue(string $value): string
    {
        return self::CODE;
    }
}
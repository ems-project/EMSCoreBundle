<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Verification;

use Symfony\Component\HttpFoundation\Request;

final class CreateVerificationRequest
{
    /** @var string */
    private $value;

    public function __construct(Request $request)
    {
        $json = \json_decode((string) $request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FormVerificationException('invalid json!');
        }

        $value = $json['value'] ?? null;

        if (null === $value) {
            throw new FormVerificationException('value is required!');
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

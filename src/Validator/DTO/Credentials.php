<?php

namespace EMS\CoreBundle\Validator\DTO;

class Credentials
{
    /** @var string */
    private $currentPassword;

    /** @var string */
    private $newPassword;

    public function __construct(string $currentPassword, string $newPassword)
    {
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }

    public function getCurrentPassword(): string
    {
        return $this->currentPassword;
    }

    public function getNewPassword(): string
    {
        return $this->newPassword;
    }
}

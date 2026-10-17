<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use Symfony\Component\Translation\TranslatableMessage;

class CantBeFinalizedException extends ElasticmsException implements \Stringable
{
    public function __construct(private readonly TranslatableMessage $translatableMessage)
    {
        parent::__construct($translatableMessage->getMessage());
    }

    #[\Override]
    public function __toString(): string
    {
        return parent::getMessage();
    }

    public function getTranslatableMessage(): TranslatableMessage
    {
        return $this->translatableMessage;
    }
}

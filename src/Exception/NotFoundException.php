<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotFoundException extends NotFoundHttpException
{
    public static function channelByName(string $channelName): self
    {
        return new self(\sprintf('Channel with name %s not found!', $channelName));
    }
}

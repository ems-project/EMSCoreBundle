<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class NotFoundException extends NotFoundHttpException
{
    public static function revisionForOuuid(string $ouuid): self
    {
        return new self(\sprintf('Revision with "%s" not found', $ouuid));
    }

    public static function revisionForDocument(DocumentInterface $document): self
    {
        return new self(\sprintf(
            'revision with id "%s" for content type "%s" not found!',
            $document->getOuuid(),
            $document->getContentType()
        ));
    }

    public static function channelByName(string $channelName): self
    {
        return new self(\sprintf('Channel with name %s not found!', $channelName));
    }
}

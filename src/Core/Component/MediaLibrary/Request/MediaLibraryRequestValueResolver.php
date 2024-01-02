<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class MediaLibraryRequestValueResolver implements ValueResolverInterface
{
    /**
     * @return iterable<MediaLibraryRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return MediaLibraryRequest::class === $argument->getType() ? [new MediaLibraryRequest($request)] : [];
    }
}

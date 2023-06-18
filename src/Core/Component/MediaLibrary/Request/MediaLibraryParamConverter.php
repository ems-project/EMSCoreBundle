<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Request;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class MediaLibraryParamConverter implements ParamConverterInterface
{
    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $request->attributes->set($configuration->getName(), new MediaLibraryRequest($request));

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return MediaLibraryRequest::class === $configuration->getClass();
    }
}

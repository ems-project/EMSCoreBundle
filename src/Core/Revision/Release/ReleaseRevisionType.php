<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Release;

enum ReleaseRevisionType: string
{
    case PUBLISH = 'publish';
    case UNPUBLISH = 'unpublish';
}

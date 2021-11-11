<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const CONTENT_TYPE_TRANSFORM = 'emsco:contenttype:transform';

    public const ENVIRONMENT_ALIGN = 'emsco:environment:align';

    public const REVISION_ARCHIVE = 'emsco:revision:archive';

    public const RELEASE_PUBLISH = 'emsco:release:publish';

    public const REVISION_TASK_CREATE = 'emsco:revision:task:create';

    public const XLIFF_EXTRACTOR = 'emsco:xliff:extract';
}

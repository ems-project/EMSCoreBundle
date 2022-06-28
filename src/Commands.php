<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const CONTENT_TYPE_TRANSFORM = 'emsco:contenttype:transform';

    public const ENVIRONMENT_ALIGN = 'emsco:environment:align';
    public const ENVIRONMENT_UNPUBLISH = 'emsco:environment:unpublish';

    public const RELEASE_PUBLISH = 'emsco:release:publish';
    public const RELEASE_CREATE = 'emsco:release:create';

    public const REVISION_ARCHIVE = 'emsco:revision:archive';
    public const REVISION_COPY = 'emsco:revision:copy';
    public const REVISION_TASK_CREATE = 'emsco:revision:task:create';
    public const REVISION_DISCARD_DRAFT = 'emsco:revision:discard-draft';

    public const XLIFF_EXTRACT = 'emsco:xliff:extract';
    public const XLIFF_UPDATE = 'emsco:xliff:update';
}

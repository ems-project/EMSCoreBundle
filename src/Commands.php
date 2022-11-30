<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const CONTENT_TYPE_TRANSFORM = 'emsco:contenttype:transform';
    public const CONTENT_TYPE_SWITCH_DEFAULT_ENV = 'emsco:contenttype:switch-default-env';

    public const ENVIRONMENT_ALIGN = 'emsco:environment:align';
    public const ENVIRONMENT_UNPUBLISH = 'emsco:environment:unpublish';

    public const RELEASE_PUBLISH = 'emsco:release:publish';
    public const RELEASE_CREATE = 'emsco:release:create';

    public const REVISION_ARCHIVE = 'emsco:revision:archive';
    public const REVISION_COPY = 'emsco:revision:copy';
    public const REVISION_DELETE = 'emsco:revision:delete';
    public const REVISION_TASK_CREATE = 'emsco:revision:task:create';
    public const REVISION_DISCARD_DRAFT = 'emsco:revision:discard-draft';

    public const USER_ACTIVATE = 'emsco:user:activate';
    public const USER_CHANGE_PASSWORD = 'emsco:user:change-password';
    public const USER_CREATE = 'emsco:user:create';
    public const USER_DEACTIVATE = 'emsco:user:deactivate';
    public const USER_DEMOTE = 'emsco:user:demote';
    public const USER_PROMOTE = 'emsco:user:promote';

    public const XLIFF_EXTRACT = 'emsco:xliff:extract';
    public const XLIFF_UPDATE = 'emsco:xliff:update';
}

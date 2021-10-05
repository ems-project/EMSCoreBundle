<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const ASSET_CLEAN = 'emsco:asset:clean';
    public const ASSET_EXTRACT = 'emsco:asset:extract';
    public const ASSET_HEAD = 'emsco:asset:head';
    public const ASSET_SYNCHRONIZE = 'emsco:asset:synchronize';

    public const CHECK_ALIASES = 'emsco:check:aliases';

    public const CONTENTTYPE_ACTIVATE = 'emsco:contenttype:activate';
    public const CONTENTTYPE_CLEAN = 'emsco:contenttype:clean';
    public const CONTENTTYPE_DELETE = 'emsco:contenttype:delete';
    public const CONTENTTYPE_EXPORT = 'emsco:contenttype:export';
    public const CONTENTTYPE_IMPORT = 'emsco:contenttype:import';
    public const CONTENTTYPE_LOCK = 'emsco:contenttype:lock';
    public const CONTENTTYPE_MIGRATE = 'emsco:contenttype:migrate';
    public const CONTENTTYPE_RECOMPUTE = 'emsco:contenttype:recompute';
    public const CONTENTTYPE_TRANSFORM = 'emsco:contenttype:transform';

    public const DELETE_ORPHANS = 'emsco:delete:orphans';

    public const ENVIRONMENT_ALIGN = 'emsco:environment:align';
    public const ENVIRONMENT_CREATE = 'emsco:environment:create';
    public const ENVIRONMENT_LIST = 'emsco:environment:list';
    public const ENVIRONMENT_REBUILD = 'emsco:environment:rebuild';
    public const ENVIRONMENT_REINDEX = 'emsco:environment:reindex';
    public const ENVIRONMENT_UPDATE_META_FIELD = 'emsco:environment:updatemetafield';

    public const JOB_RUN = 'emsco:job:run';

    public const MANAGED_ALIAS_ALIGN = 'emsco:managedalias:align';
    public const MANAGED_ALIAS_LIST = 'emsco:managedalias:list';

    public const NOTIFICATION_BULK_ACTION = 'emsco:notification:bulk-action';
    public const NOTIFICATION_SEND = 'emsco:notification:send';

    public const REVISION_ARCHIVE = 'emsco:revision:archive';
    public const REVISION_COPY = 'emsco:revision:copy';
    public const REVISION_TASK_CREATE = 'emsco:revision:task:create';
    public const REVISION_TIME_MACHINE = 'emsco:revision:time-machine';
    public const REVISION_INDEX_FILE_FIELDS = 'emsco:revision:index-file-fields';
    public const REVISION_UNLOCK = 'emsco:revision:unlock';

    public const SUBMISSION_EMAIL = 'emsco:submission:email';
    public const SUBMISSION_REMOVE_EXPIRED = 'emsco:submission:remove-expired';
}

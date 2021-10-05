<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const CONTENT_TYPE_TRANSFORM = 'emsco:contenttype:transform';

    public const ENVIRONMENT_ALIGN = 'emsco:environment:align';

    public const REVISION_TASK_CREATE = 'emsco:revision:task:create';

    public const EMS_ASSET_HEAD = 'ems:asset:head';
    public const EMS_CHECK_ALIASES = 'ems:check:aliases';
    public const EMS_NOTIFICATION_BULK_ACTION = 'ems:notification:bulk-action';
    public const EMS_NOTIFICATION_SEND = 'ems:notification:send';
    public const EMS_REVISION_TIME_MACHINE = 'ems:revision:time-machine';
    public const EMS_CONTENTTYPE_ACTIVATE = 'ems:contenttype:activate';
    public const EMS_MANAGEDALIAS_ALIGN = 'ems:managedalias:align';
    public const EMS_ASSET_CLEAN = 'ems:asset:clean';
    public const EMS_CONTENTTYPE_CLEAN = 'ems:contenttype:clean';
    public const EMS_ENVIRONMENT_CREATE = 'ems:environment:create';
    public const EMS_CONTENTTYPE_DELETE = 'ems:contenttype:delete';
    public const EMS_DELETE_ORPHANS = 'ems:delete:orphans';
    public const EMS_CONTENTTYPE_IMPORT = 'ems:contenttype:import';
    public const EMS_SUBMISSIONS_EMAIL = 'ems:submissions:email';
    public const EMS_ENVIRONMENT_LIST = 'ems:environment:list';
    public const EMS_CONTENTTYPE_EXPORT = 'ems:contenttype:export';
    public const EMS_ASSET_EXTRACT = 'ems:asset:extract';
    public const EMS_REVISIONS_INDEX_FILE_FIELDS = 'ems:revisions:index-file-fields';
    public const EMS_JOB_RUN = 'ems:job:run';
    public const EMS_CONTENTTYPE_LOCK = 'ems:contenttype:lock';
    public const EMS_MANAGEDALIAS_LIST = 'ems:managedalias:list';
    public const EMS_CONTENTTYPE_MIGRATE = 'ems:contenttype:migrate';
    public const EMS_ENVIRONMENT_REBUILD = 'ems:environment:rebuild';
    public const EMS_CONTENTTYPE_RECOMPUTE = 'ems:contenttype:recompute';
    public const EMS_ENVIRONMENT_REINDEX = 'ems:environment:reindex';
    public const EMS_SUBMISSIONS_REMOVE_EXPIRED = 'ems:submissions:remove-expired';
    public const EMS_REVISION_COPY = 'ems:revision:copy';
    public const EMS_ASSET_SYNCHRONIZE = 'ems:asset:synchronize';
    public const EMS_REVISIONS_UNLOCK = 'ems:revisions:unlock';
    public const EMS_ENVIRONMENT_UPDATEMETAFIELD = 'ems:environment:updatemetafield';
    public const REVISION_ARCHIVE = 'emsco:revision:archive';
}

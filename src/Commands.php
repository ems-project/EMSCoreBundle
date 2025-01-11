<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

final class Commands
{
    public const string ASSET_SYNCHRONIZE = 'emsco:asset:synchronize';
    public const string ASSET_EXTRACT = 'emsco:asset:extract';
    public const string ASSET_CLEAN = 'emsco:asset:clean';
    public const string ASSET_HEAD = 'emsco:asset:head';
    public const string CONTENT_TYPE_LOCK = 'ems:contenttype:lock';
    public const string CONTENT_TYPE_RECOMPUTE = 'ems:contenttype:recompute';
    public const string CONTENT_TYPE_SWITCH_DEFAULT_ENV = 'emsco:contenttype:switch-default-env';
    public const string CONTENT_TYPE_CLEAN = 'emsco:contenttype:clean';
    public const string CONTENT_TYPE_ACTIVATE = 'emsco:contenttype:activate';
    public const string CONTENT_TYPE_MIGRATE = 'emsco:contenttype:migrate';
    public const string CONTENT_TYPE_IMPORT = 'emsco:contenttype:import';
    public const string CONTENT_TYPE_TRANSFORM = 'emsco:contenttype:transform';
    public const string CONTENT_TYPE_EXPORT = 'emsco:contenttype:export';
    public const string DELETE_ORPHANS = 'emsco:delete:orphans';

    public const string ENVIRONMENT_ALIGN = 'emsco:environment:align';
    public const string ENVIRONMENT_UNPUBLISH = 'emsco:environment:unpublish';
    public const string ENVIRONMENT_CREATE = 'emsco:environment:create';
    public const string ENVIRONMENT_REINDEX = 'emsco:environment:reindex';
    public const string ENVIRONMENT_LIST = 'emsco:environment:list';
    public const string ENVIRONMENT_UPDATE_META_FIELD = 'emsco:environment:update-meta-field';
    public const string ENVIRONMENT_REBUILD = 'emsco:environment:rebuild';
    public const string JOB_RUN = 'emsco:job:run';

    public const string MANAGED_ALIAS_CREATE = 'emsco:managed-alias:create';
    public const string MANAGED_ALIAS_ADD_ENVIRONMENT = 'emsco:managed-alias:add-environment';
    public const string MANAGED_ALIAS_LIST = 'emsco:managed-alias:list';
    public const string MANAGED_ALIAS_ALIGN = 'emsco:managed-alias:align';
    public const string MANAGED_ALIAS_CHECK = 'emsco:managed-alias:check';
    public const string NOTIFICATION_BULK_ACTION = 'emsco:notification:bulk-action';
    public const string NOTIFICATION_SEND = 'emsco:notification:send';

    public const string RELEASE_CREATE = 'emsco:release:create';

    public const string REVISION_ARCHIVE = 'emsco:revision:archive';
    public const string REVISION_COPY = 'emsco:revision:copy';
    public const string REVISION_DELETE = 'emsco:revision:delete';
    public const string REVISION_TASK_CREATE = 'emsco:revision:task:create';
    public const string REVISION_TASK_NOTIFICATION_MAIL = 'emsco:revision:task:notification-mail';
    public const string REVISION_DISCARD_DRAFT = 'emsco:revision:discard-draft';
    public const string REVISIONS_UNLOCK = 'emsco:revisions:unlock';
    public const string REVISIONS_INDEX_FILE_FIELDS = 'emsco:revisions:index-file-fields';
    public const string REVISIONS_TIME_MACHINE = 'emsco:revisions:time-machine';
    public const string SUBMISSIONS_EMAIL = 'emsco:submissions:email';
    public const string SUBMISSIONS_REMOVE_EXPIRED = 'emsco:submissions:remove-expired';

    public const string MEDIA_LIB_FOLDER_DELETE = 'emsco:medialib:folder-delete';
    public const string MEDIA_LIB_FOLDER_RENAME = 'emsco:medialib:folder-rename';

    public const string USER_ACTIVATE = 'emsco:user:activate';
    public const string USER_CHANGE_PASSWORD = 'emsco:user:change-password';
    public const string USER_CREATE = 'emsco:user:create';
    public const string USER_DEACTIVATE = 'emsco:user:deactivate';
    public const string USER_DEMOTE = 'emsco:user:demote';
    public const string USER_PROMOTE = 'emsco:user:promote';
    public const string USER_UPDATE_OPTION = 'emsco:user:update-option';

    public const string XLIFF_EXTRACT = 'emsco:xliff:extract';
    public const string XLIFF_UPDATE = 'emsco:xliff:update';

    public const string ASSET_REFRESH_FILE_FIELD = 'emsco:asset:refresh-file-fields';
    final public const string SUBMISSION_EXPORT = 'emsco:submissions:export';
}

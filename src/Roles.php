<?php

declare(strict_types=1);

namespace EMS\CoreBundle;

use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

class Roles
{
    final public const string NOT_DEFINED = 'not-defined';

    final public const string ROLE_API = 'ROLE_API';
    final public const string ROLE_USER = 'ROLE_USER';
    final public const string ROLE_AUTHOR = 'ROLE_AUTHOR';
    final public const string ROLE_FORM_CRM = 'ROLE_FORM_CRM';
    final public const string ROLE_TASK_MANAGER = 'ROLE_TASK_MANAGER';
    final public const string ROLE_ALLOW_ALIGN = 'ROLE_ALLOW_ALIGN';
    final public const string ROLE_COPY_PASTE = 'ROLE_COPY_PASTE';
    final public const string ROLE_DEFAULT_SEARCH = 'ROLE_DEFAULT_SEARCH';
    final public const string ROLE_REVIEWER = 'ROLE_REVIEWER';
    final public const string ROLE_TRADUCTOR = 'ROLE_TRADUCTOR';
    final public const string ROLE_COPYWRITER = 'ROLE_COPYWRITER';
    final public const string ROLE_AUDITOR = 'ROLE_AUDITOR';
    final public const string ROLE_PUBLISHER = 'ROLE_PUBLISHER';
    final public const string ROLE_WEBMASTER = 'ROLE_WEBMASTER';
    final public const string ROLE_USER_MANAGEMENT = 'ROLE_USER_MANAGEMENT';
    final public const string ROLE_ADMIN = 'ROLE_ADMIN';
    final public const string ROLE_SUPER = 'ROLE_SUPER';
    final public const string ROLE_SUPER_USER = 'ROLE_SUPER_USER';
    final public const string ROLE_USER_READ = 'ROLE_USER_READ';
    final public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    public static function translate(string $role): TranslatableMessage
    {
        return match ($role) {
            self::NOT_DEFINED => t('role.not_defined.label', [], 'emsco-core'),
            self::ROLE_API => t('role.api.label', [], 'emsco-core'),
            self::ROLE_USER => t('role.user.label', [], 'emsco-core'),
            self::ROLE_AUTHOR => t('role.author.label', [], 'emsco-core'),
            self::ROLE_FORM_CRM => t('role.form_crm.label', [], 'emsco-core'),
            self::ROLE_TASK_MANAGER => t('role.task_manager.label', [], 'emsco-core'),
            self::ROLE_ALLOW_ALIGN => t('role.allow_align.label', [], 'emsco-core'),
            self::ROLE_COPY_PASTE => t('role.copy_paste.label', [], 'emsco-core'),
            self::ROLE_DEFAULT_SEARCH => t('role.default_search.label', [], 'emsco-core'),
            self::ROLE_REVIEWER => t('role.reviewer.label', [], 'emsco-core'),
            self::ROLE_TRADUCTOR => t('role.traductor.label', [], 'emsco-core'),
            self::ROLE_COPYWRITER => t('role.copywriter.label', [], 'emsco-core'),
            self::ROLE_AUDITOR => t('role.auditor.label', [], 'emsco-core'),
            self::ROLE_PUBLISHER => t('role.publisher.label', [], 'emsco-core'),
            self::ROLE_WEBMASTER => t('role.webmaster.label', [], 'emsco-core'),
            self::ROLE_USER_MANAGEMENT => t('role.user_management.label', [], 'emsco-core'),
            self::ROLE_ADMIN => t('role.admin.label', [], 'emsco-core'),
            self::ROLE_SUPER => t('role.super.label', [], 'emsco-core'),
            self::ROLE_SUPER_USER => t('role.super_user.label', [], 'emsco-core'),
            self::ROLE_USER_READ => t('role.user_read.label', [], 'emsco-core'),
            self::ROLE_SUPER_ADMIN => t('role.super_admin.label', [], 'emsco-core'),
            default => new TranslatableMessage($role),
        };
    }

    public static function description(string $role): TranslatableMessage
    {
        return match ($role) {
            self::ROLE_API => t('role.api.description', [], 'emsco-core'),
            self::ROLE_FORM_CRM => t('role.form_crm.description', [], 'emsco-core'),
            self::ROLE_TASK_MANAGER => t('role.task_manager.description', [], 'emsco-core'),
            self::ROLE_ALLOW_ALIGN => t('role.allow_align.description', [], 'emsco-core'),
            self::ROLE_COPY_PASTE => t('role.copy_paste.description', [], 'emsco-core'),
            self::ROLE_DEFAULT_SEARCH => t('role.default_search.description', [], 'emsco-core'),
            self::ROLE_USER_MANAGEMENT => t('role.user_management.description', [], 'emsco-core'),
            self::ROLE_SUPER_USER => t('role.super_user.description', [], 'emsco-core'),
            self::ROLE_USER_READ => t('role.user_read.description', [], 'emsco-core'),
            default => new TranslatableMessage($role),
        };
    }
}

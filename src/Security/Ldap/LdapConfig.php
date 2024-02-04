<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Ldap;

use EMS\CoreBundle\Roles;

final class LdapConfig
{
    public string $dnString;
    public string $baseDn;
    public string $searchDn;
    public string $searchPassword;

    /** @var string[] */
    public array $defaultRoles;
    public ?string $uidKey;
    public ?string $filter;
    public ?string $passwordAttribute;

    /** @var string[] */
    public array $extraFields;
    public ?string $emailField;
    public ?string $displayNameField;

    /**
     * @param array{
     *     dn_string?: ?string,
     *     base_dn?: ?string,
     *     search_dn?: ?string,
     *     search_password?: ?string,
     *     default_roles?: string[],
     *     uid_key?: ?string,
     *     filter?: ?string,
     *     password_attribute?: ?string,
     *     extra_fields?: string[],
     *     email_field?: ?string,
     *     display_name_field?: ?string
     * } $config
     */
    public function __construct(array $config)
    {
        $this->dnString = $config['dn_string'] ?? '{username}';
        $this->baseDn = $config['base_dn'] ?? '';
        $this->searchDn = $config['search_dn'] ?? '';
        $this->searchPassword = $config['search_password'] ?? '';

        $this->passwordAttribute = ($config['password_attribute'] ?? '') !== '' ? $config['password_attribute'] : null;
        $this->filter = ($config['filter'] ?? '') !== '' ? $config['filter'] : null;

        $this->defaultRoles = $config['default_roles'] ?? [Roles::ROLE_USER];
        $this->extraFields = $config['extra_fields'] ?? [];

        $this->emailField = ($config['email_field'] ?? '') !== '' ? $config['email_field'] : null;
        $this->displayNameField = ($config['display_name_field'] ?? '') !== '' ? $config['display_name_field'] : null;
        $this->uidKey = ($config['uid_key'] ?? '') !== '' ? $config['uid_key'] : null;
    }
}

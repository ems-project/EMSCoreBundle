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

    public ?string $passwordAttribute;
    public ?string $filter;

    /** @var string[] */
    public array $defaultRoles;
    /** @var string[] */
    public array $extraFields;

    public ?string $emailField;
    public ?string $displayNameField;
    public ?string $givenNameField;
    public ?string $lastNameField;
    public ?string $uidKey;

    /**
     * @param array{
     *     dn_string?: ?string,
     *     base_dn?: ?string,
     *     search_dn?: ?string,
     *     search_password?: ?string,
     *     password_attribute?: ?string,
     *     filter?: ?string,
     *     default_roles?: string[],
     *     extra_fields?: string[],
     *     email_field?: ?string,
     *     display_name_field?: ?string,
     *     given_name_field?: ?string,
     *     last_name_field?: ?string,
     *     uid_key?: ?string
     * } $config
     */
    public function __construct(array $config)
    {
        $this->dnString = $config['dn_string'] ?? '{username}';
        $this->baseDn = $config['base_dn'] ?? '';
        $this->searchDn = $config['search_dn'] ?? '';
        $this->searchPassword = $config['search_password'] ?? '';

        $this->passwordAttribute = $config['password_attribute'] ?? null;
        $this->filter = $config['filter'] ?? null;

        $this->defaultRoles = $config['default_roles'] ?? [Roles::ROLE_USER];
        $this->extraFields = $config['extra_fields'] ?? [];

        $this->emailField = $config['email_field'] ?? null;
        $this->displayNameField = $config['display_name_field'] ?? null;
        $this->givenNameField = $config['given_name_field'] ?? null;
        $this->lastNameField = $config['last_name_field'] ?? null;
        $this->uidKey = $config['uid_key'] ?? null;
    }
}

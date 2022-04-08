#User

## User Settings

The user settings is a field available in the 'User Management' tab.

This field accepts string and should be filled with a Json. It allows the developer to add some information about the user to refine his personalisation.

##Example

```json
{
  "schedule": [4,8,8,8,4],
  "status": "intern"
}
```
###Usage of those data

Get all the enabled users in alpha order
```twig
{% set usersEnabled = emsco_users_enabled() %}
{% set orderedUsers = [] %}

{% for user in usersEnabled.getUsers|sort((a, b) => a.username <=> b.username) %}
    {% set orderedUsers = orderedUsers|merge( [ user ] ) %}
{% endfor %}
```

Access the data
```twig
{% for user in orderedUsers %}
    {% for key, values in user.settings|ems_json_decode %}
        {{ key~' : ' }}
        {% if values is iterable %}
            {% for value in values %}
                {{ value }}
            {% endfor %}
        {% else %}
            {{ values }}
        {% endif %}
    {% endfor %}
{% endfor %}
```
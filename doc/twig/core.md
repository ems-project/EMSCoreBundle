# Core

## emsco_log_[error | warning | notice]
Print flash message, usefull in post processing
```twig
{{ 'Example print error flash'|emsco_log_error }}
{{ 'Example print warning flash'|emsco_log_warning }}
{{ 'Example print notice flash'|emsco_log_notice }}
```
## emsco_generate_email
Generate an email and use [emsco_send_email](#emsco_send_email) for sending the email.
```twig
{% set mailBody %}
  <h1>Example email</h1>
  <p>example ...</p>
{% endset %}

{% set email = emsco_generate_email('test title') %}
{% do email.to('test@example.com').html(mailBody|format) %}
{% do emsco_send_email(email) %}
```

## emsco_send_email
Send an email generated with [emsco_generate_email](#emsco_generate_email).
Default value for from is `ems_core.from_email` and `%ems_core.name%` parameter.

```twig
{% set email = emsco_generate_email('example send') %}
{% do email.to('test@example.com').text('Body text') %}
{% do emsco_send_email(email) %}
```
CoreBundle
=============

[Api documentation](../master/doc/api.md)
[Elasticms documentation](../master/doc/elasticms.md)

Coding standards
----------------
PHP Code Sniffer is available via composer, the standard used is defined in phpcs.xml.diff:
````bash
composer phpcs
````

If your code is not compliant, you could try fixing it automatically:
````bash
composer phpcbf
````

PHPStan is configured at level 3, you can check for errors locally using:
`````bash
composer phpstan
`````

Controller/ApplController.php is excluded 
Please take some time to refactor the deprecated functions to use dependency injection instead of getting services from the container

If you want to regenerate a PHPStan baseline run this command:
```
vendor/bin/phpstan analyse --generate-baseline
```


## Update translation files

```
demo-dev trans:update --force --output-format=yml --sort=asc en EMSCoreBundle
demo-dev trans:update --force --domain=emsco-twigs --output-format=yml --sort=asc en EMSCoreBundle
```



Documentation
-------------
* [Installation](../master/doc/install.md)
* [API](../master/doc/api.md)
* [Todo](../master/doc/todo.md)

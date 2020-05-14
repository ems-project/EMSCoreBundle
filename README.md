CoreBundle
=============

[Api documentation](../master/Resources/doc/api.md)

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

Documentation
-------------
symfony new your_app
cd your_app
 
composer require friendsofsymfony/user-bundle:2.0.x-dev
composer require elasticms/core-bundle:dev-master
 
 
        	new FOS\UserBundle\FOSUserBundle(),
        	new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
            new EMS\CoreBundle\EMSCoreBundle(),
            
            
            in AppKernel.php
            
            
            ems_core:
    resource: "@EMSCoreBundle/Controller/"
    type:     annotation
#    prefix:   /admin

fos_user:
    resource: "@FOSUserBundle/Resources/config/routing/all.xml"    

app:
    resource: "@AppBundle/Controller/"
    type:     annotation
            
            in routing.yml
            
            
fos_user:
    db_driver: orm # other valid values are 'mongodb', 'couchdb' and 'propel'
    firewall_name: main
    user_class: EMS\CoreBundle\Entity\User
    from_email: 
        address: noreply@example.com
        sender_name: elasticMS
    service:
        mailer: fos_user.mailer.default
    resetting:
        email:
            template: email/password_resetting.email.twig
    profile:
        form:
            type: AppBundle\Form\Form\UserProfileType
            
ems_core:
    theme_color: red
    circles_object: institution
    instance_id: calbe_
    
    in config.yml
    
Todo
----
- FOSUserBundle deprecation [Issue 2803](https://github.com/FriendsOfSymfony/FOSUserBundle/issues/2803)
```
The "FOS\UserBundle\Model\UserInterface" interface extends "Symfony\Component\Security\Core\User\AdvancedUserInterface" 
that is deprecated since Symfony 4.1 *.
```

<p align="center">
    <img src="https://github.com/ems-project/EMSCoreBundle/blob/master/Resources/public/images/elasticms_ball_only_circlewhite.png">
</p>

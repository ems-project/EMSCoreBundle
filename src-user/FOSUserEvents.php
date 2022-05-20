<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\UserBundle;

/**
 * Contains all events thrown in the FOSUserBundle.
 */
final class FOSUserEvents
{
    /**
     * The USER_CREATED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the created user and to add some behaviour after the creation.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_CREATED = 'fos_user.user.created';

    /**
     * The USER_PASSWORD_CHANGED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the created user and to add some behaviour after the password change.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_PASSWORD_CHANGED = 'fos_user.user.password_changed';

    /**
     * The USER_ACTIVATED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the activated user and to add some behaviour after the activation.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_ACTIVATED = 'fos_user.user.activated';

    /**
     * The USER_DEACTIVATED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the deactivated user and to add some behaviour after the deactivation.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_DEACTIVATED = 'fos_user.user.deactivated';

    /**
     * The USER_PROMOTED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the promoted user and to add some behaviour after the promotion.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_PROMOTED = 'fos_user.user.promoted';

    /**
     * The USER_DEMOTED event occurs when the user is created with UserManipulator.
     *
     * This event allows you to access the demoted user and to add some behaviour after the demotion.
     *
     * @Event("FOS\UserBundle\Event\UserEvent")
     */
    public const USER_DEMOTED = 'fos_user.user.demoted';
}

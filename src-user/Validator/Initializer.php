<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\UserBundle\Validator;

use EMS\CoreBundle\Core\Security\Canonicalizer;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;

/**
 * Automatically updates the canonical fields before validation.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class Initializer implements ObjectInitializerInterface
{
    /**
     * @param object $object
     */
    public function initialize($object)
    {
        if ($object instanceof UserInterface) {
            $object->setUsernameCanonical(Canonicalizer::canonicalize($object->getUsername()));
            $object->setEmailCanonical(Canonicalizer::canonicalize($object->getEmail()));
        }
    }
}

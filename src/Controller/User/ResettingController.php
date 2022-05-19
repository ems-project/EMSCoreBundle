<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    public function request(): Response
    {
        return $this->render('@EMSCore/user/resetting/request.html.twig');
    }
}

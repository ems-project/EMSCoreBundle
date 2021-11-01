<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('@EMSCore/dashboard/index.html.twig', [
        ]);
    }
}

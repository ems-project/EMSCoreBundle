<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ChangePasswordType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordController extends AbstractController
{
    private UserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(UserManager $userManager, LoggerInterface $logger)
    {
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->userManager->getAuthenticatedUser();

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->update($user);
            $this->logger->notice('log.user.password_updated');

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return $this->render('@EMSCore/user/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ResettingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    private UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    public function request(Request $request): Response
    {
        $form = $this->createForm(ResettingType::class, []);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $usernameOrEmail = $form->get('username_email')->getData();
            $user = $this->userManager->requestResetPassword($usernameOrEmail);

            if ($user) {
                return $this->redirectToRoute('emsco_user_resetting_check_email', [
                    'email' => $user->getEmail(),
                ]);
            }
        }

        return $this->render('@EMSCore/user/resetting/request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function checkEmail(Request $request): Response
    {
        $email = $request->query->get('email');

        if (null === $email) {
            return $this->redirectToRoute('emsco_user_resetting_request');
        }

        return $this->render('@EMSCore/user/resetting/check_email.html.twig', [
            'tokenLifetime' => UserManager::PASSWORD_RETRY_TTL,
        ]);
    }
}

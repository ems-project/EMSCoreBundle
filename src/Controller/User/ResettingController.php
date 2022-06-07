<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ResettingRequestType;
use EMS\CoreBundle\Form\User\ResettingResetType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    private UserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(UserManager $userManager, LoggerInterface $logger)
    {
        $this->userManager = $userManager;
        $this->logger = $logger;
    }

    public function request(Request $request): Response
    {
        $form = $this->createForm(ResettingRequestType::class, []);
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

    public function reset(Request $request, string $token): Response
    {
        if (null === $user = $this->userManager->getUserByConfirmationToken($token)) {
            return $this->redirectToRoute('fos_user_security_login');
        }

        if (!$user->isPasswordRequestNonExpired(UserManager::PASSWORD_RETRY_TTL)) {
            return $this->redirectToRoute('emsco_user_resetting_request');
        }

        $form = $this->createForm(ResettingResetType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $this->redirectToRoute(Routes::USER_PROFILE);

            $this->userManager->resetPassword($user, $response);

            $this->logger->notice('log.user.password_resetted');

            return $response;
        }

        return $this->render('@EMSCore/user/resetting/reset.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

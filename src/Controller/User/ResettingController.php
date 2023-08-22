<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ResettingRequestType;
use EMS\CoreBundle\Form\User\ResettingResetType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Security\Authenticator\Authenticator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResettingController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager, private readonly Authenticator $authenticator, private readonly LoggerInterface $logger, private readonly string $templateNamespace)
    {
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

        return $this->render("@$this->templateNamespace/user/resetting/request.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function checkEmail(Request $request): Response
    {
        $email = $request->query->get('email');

        if (null === $email) {
            return $this->redirectToRoute('emsco_user_resetting_request');
        }

        return $this->render("@$this->templateNamespace/user/resetting/check_email.html.twig", [
            'tokenLifetime' => UserManager::PASSWORD_RETRY_TTL,
        ]);
    }

    public function reset(Request $request, string $token): Response
    {
        if (null === $user = $this->userManager->getUserByConfirmationToken($token)) {
            return $this->redirectToRoute(Routes::USER_LOGIN);
        }

        if (!$user->isPasswordRequestNonExpired(UserManager::PASSWORD_RETRY_TTL)) {
            return $this->redirectToRoute('emsco_user_resetting_request');
        }

        $form = $this->createForm(ResettingResetType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->resetPassword($user);
            $this->authenticator->authenticate($user);
            $this->logger->notice('log.user.resetting.success');

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return $this->render("@$this->templateNamespace/user/resetting/reset.html.twig", [
            'form' => $form->createView(),
        ]);
    }
}

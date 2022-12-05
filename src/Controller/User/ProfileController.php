<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ChangePasswordType;
use EMS\CoreBundle\Form\User\UserProfileType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager, private readonly LoggerInterface $logger)
    {
    }

    public function show(): Response
    {
        return $this->render('@EMSCore/user/profile/show.html.twig', [
            'user' => $this->userManager->getAuthenticatedUser(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $user = $this->userManager->getAuthenticatedUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->update($user);
            $this->logger->notice('log.user.profile.updated');

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return $this->render('@EMSCore/user/profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->userManager->getAuthenticatedUser();

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->update($user);
            $this->logger->notice('log.user.profile.changed_password');

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return $this->render('@EMSCore/user/profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

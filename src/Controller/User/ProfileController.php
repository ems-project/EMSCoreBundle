<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ChangePasswordType;
use EMS\CoreBundle\Form\User\UserProfileType;
use EMS\CoreBundle\Routes;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class ProfileController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager, private readonly LoggerInterface $logger, private readonly string $templateNamespace)
    {
    }

    public function show(): Response
    {
        return $this->render(\sprintf('@%s/user/profile/show.html.twig', $this->templateNamespace), [
            'user' => $this->userManager->getAuthenticatedUser(),
            'title' => t('profile.title', [], 'emsco-core'),
            'subTitle' => t('profile.title_sub', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
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

        return $this->render(\sprintf('@%s/user/profile/edit.html.twig', $this->templateNamespace), [
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

        return $this->render(\sprintf('@%s/user/profile/change_password.html.twig', $this->templateNamespace), [
            'form' => $form->createView(),
        ]);
    }

    private function breadcrumb(): Navigation
    {
        return new Navigation()->add(
            label: t('profile.title', [], 'emsco-core'),
            icon: 'fa fa-user',
            route: Routes::USER_PROFILE,
        );
    }
}

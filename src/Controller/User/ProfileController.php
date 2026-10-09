<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Form\User\ChangePasswordType;
use EMS\CoreBundle\Form\User\UserProfileType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly LocalizedLoggerInterface $logger,
    ) {
    }

    public function show(): Page
    {
        return new Page(
            context: [
                'user' => $this->userManager->getAuthenticatedUser(),
                'title' => t('title.profile', [], 'emsco-core'),
                'subTitle' => t('title.profile_tagline', [], 'emsco-core'),
                'breadcrumb' => $this->breadcrumb(),
                'width' => 'narrow',
            ],
            template: 'page/user_profile.html.twig'
        );
    }

    public function edit(Request $request): Page|RedirectResponse
    {
        $user = $this->userManager->getAuthenticatedUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->update($user);
            $this->logger->messageNotice(t('message.user_profile_updated', [], 'emsco-core'));

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('title.edit_your_profile', [], 'emsco-core'),
            'subTitle' => t('title.profile_tagline', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(t('action.edit', [], 'emsco-core')),
        ]);
    }

    public function changePassword(Request $request): Page|RedirectResponse
    {
        $user = $this->userManager->getAuthenticatedUser();

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->update($user);
            $this->logger->messageNotice(t('message.user_profile_changed_password', [], 'emsco-core'));

            return $this->redirectToRoute(Routes::USER_PROFILE);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('title.change_your_password', [], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(t('title.change_your_password', [], 'emsco-core')),
            'width' => 'narrow',
        ]);
    }

    private function breadcrumb(): Navigation
    {
        return new Navigation()->home()->add(
            label: t('title.profile', [], 'emsco-core'),
            icon: 'fa fa-user',
            route: Routes::USER_PROFILE,
        );
    }
}

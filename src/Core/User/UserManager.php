<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use FOS\UserBundle\Model\UserManagerInterface as FosUserManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class UserManager
{
    private TokenStorageInterface $tokenStorage;
    private FosUserManager $fosUserManager;
    private MailerService $mailerService;

    public const PASSWORD_RETRY_TTL = 7200;
    public const CONFIRMATION_TOKEN_TTL = 86400;
    private const MAIL_TEMPLATE = '@EMSCore/user/mail.twig';

    public function __construct(
        TokenStorageInterface $tokenStorage,
        FosUserManager $fosUserManager,
        MailerService $mailerService
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->fosUserManager = $fosUserManager;
        $this->mailerService = $mailerService;
    }

    public function getAuthenticatedUser(): User
    {
        $token = $this->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('Invalid user!');
        }

        return $user;
    }

    public function requestResetPassword(string $usernameOrEmail): ?User
    {
        $user = $this->fosUserManager->findUserByUsernameOrEmail($usernameOrEmail);

        if (!$user instanceof User || !$user->isPasswordRequestNonExpired(self::PASSWORD_RETRY_TTL)) {
            return null;
        }

        if (null === $user->getConfirmationToken()) {
            $user->setConfirmationToken($this->generateToken());
        }

        $mailTemplate = $this->mailerService->makeMailTemplate(self::MAIL_TEMPLATE);
        $mailTemplate
            ->addTo($user->getEmail())
            ->setSubject('user.resetting.email.subject', ['username' => $user->getUsername()], EMSCoreBundle::TRANS_USER_DOMAIN)
            ->setBodyBlock('resetPassword', ['user' => $user])
        ;

        $this->mailerService->sendMailTemplate($mailTemplate, 'text/plain');

        $user->setPasswordRequestedAt(new \DateTime());
        $this->update($user);

        return $user;
    }

    public function update(User $user): void
    {
        $this->fosUserManager->updateUser($user);
    }

    private function getToken(): TokenInterface
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            throw new \RuntimeException('Token is null, could not get the currentUser from token.');
        }

        return $token;
    }

    private function generateToken(): string
    {
        return \rtrim(\strtr(\base64_encode(\random_bytes(32)), '+/', '-_'), '=');
    }
}

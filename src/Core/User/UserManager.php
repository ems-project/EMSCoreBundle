<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Security\Token;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Security\LoginManager;
use FOS\UserBundle\Model\UserManagerInterface as FosUserManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class UserManager
{
    private TokenStorageInterface $tokenStorage;
    private FosUserManager $fosUserManager;
    private LoginManager $loginManager;
    private MailerService $mailerService;
    private UserRepository $userRepository;

    public const PASSWORD_RETRY_TTL = 7200;
    public const CONFIRMATION_TOKEN_TTL = 86400;
    private const MAIL_TEMPLATE = '@EMSCore/user/mail.twig';

    public function __construct(
        TokenStorageInterface $tokenStorage,
        FosUserManager $fosUserManager,
        LoginManager $loginManager,
        MailerService $mailerService,
        UserRepository $userRepository
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->fosUserManager = $fosUserManager;
        $this->loginManager = $loginManager;
        $this->mailerService = $mailerService;
        $this->userRepository = $userRepository;
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

    public function getUserByConfirmationToken(string $token): ?User
    {
        $user = $this->userRepository->findOneBy(['confirmationToken' => $token]);

        return $user instanceof User ? $user : null;
    }

    public function requestResetPassword(string $usernameOrEmail): ?User
    {
        $user = $this->userRepository->findUserByUsernameOrEmail($usernameOrEmail);

        if (!$user instanceof User) {
            return null;
        }

        if ($user->isPasswordRequestNonExpired(self::PASSWORD_RETRY_TTL)) {
            return $user;
        }

        if (null === $user->getConfirmationToken()) {
            $user->setConfirmationToken(Token::generate());
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

    public function resetPassword(User $user, Response $response): void
    {
        $user->setConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $user->setEnabled(true);
        $this->update($user);

        $this->loginManager->logInUser($user, $response);

        $user->setLastLogin(new \DateTime());
        $this->update($user);
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
}

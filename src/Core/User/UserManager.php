<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Security\Canonicalizer;
use EMS\CoreBundle\Core\Security\Token;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Security\LoginManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

final class UserManager
{
    private TokenStorageInterface $tokenStorage;
    private LoginManager $loginManager;
    private MailerService $mailerService;
    private UserRepository $userRepository;
    private UserPasswordEncoderInterface $passwordEncoder;

    public const PASSWORD_RETRY_TTL = 7200;
    public const CONFIRMATION_TOKEN_TTL = 86400;
    private const MAIL_TEMPLATE = '@EMSCore/user/mail.twig';

    public function __construct(
        TokenStorageInterface $tokenStorage,
        LoginManager $loginManager,
        MailerService $mailerService,
        UserRepository $userRepository,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->loginManager = $loginManager;
        $this->mailerService = $mailerService;
        $this->userRepository = $userRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function create(string $username, string $password, string $email, bool $active, bool $superAdmin): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $user->setEnabled($active);
        $user->setSuperAdmin($superAdmin);
        $this->update($user);

        return $user;
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

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['emailCanonical' => Canonicalizer::canonicalize($email)]);
    }

    public function getUserByUsername(string $username): ?User
    {
        return $this->userRepository->findOneBy(['usernameCanonical' => Canonicalizer::canonicalize($username)]);
    }

    public function getUserByConfirmationToken(string $token): ?User
    {
        return $this->userRepository->findOneBy(['confirmationToken' => $token]);
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
        $user->setUsernameCanonical(Canonicalizer::canonicalize($user->getUsername()));
        $user->setEmailCanonical(Canonicalizer::canonicalize($user->getEmail()));

        $this->hashPassword($user);

        $this->userRepository->save($user);
    }

    public function updateEnabled(string $username, bool $enabled): void
    {
        $user = $this->userRepository->findUserByUsernameOrThrowException($username);
        $user->setEnabled($enabled);
        $this->update($user);
    }

    public function updatePassword(string $username, string $plainPassword): void
    {
        $user = $this->userRepository->findUserByUsernameOrThrowException($username);
        $user->setPlainPassword($plainPassword);
        $this->update($user);
    }

    public function updateRoleAdd(string $username, string $role): bool
    {
        $user = $this->userRepository->findUserByUsernameOrThrowException($username);

        if ($user->hasRole($role)) {
            return false;
        }

        $user->addRole($role);
        $this->update($user);

        return true;
    }

    public function updateRoleRemove(string $username, string $role): bool
    {
        $user = $this->userRepository->findUserByUsernameOrThrowException($username);

        if (!$user->hasRole($role)) {
            return false;
        }

        $user->removeRole($role);
        $this->update($user);

        return true;
    }

    public function updateSuperAdmin(string $username, bool $superAdmin): void
    {
        $user = $this->userRepository->findUserByUsernameOrThrowException($username);
        $user->setSuperAdmin($superAdmin);
        $this->update($user);
    }

    private function hashPassword(User $user): void
    {
        if (null === $plainPassword = $user->getPlainPassword()) {
            return;
        }

        $user->setSalt(Token::generate());
        $hashedPassword = $this->passwordEncoder->encodePassword($user, $plainPassword);

        $user->setPassword($hashedPassword);
        $user->eraseCredentials();
    }

    private function getToken(): TokenInterface
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            throw new \RuntimeException('Token is null, could not get the currentUser from token.');
        }

        return $token;
    }
}
<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\User;

use EMS\CoreBundle\Core\Mail\MailerService;
use EMS\CoreBundle\Core\Security\Canonicalizer;
use EMS\CoreBundle\Core\Security\Token;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Exception\NotFoundException;
use EMS\CoreBundle\Repository\AuthTokenRepository;
use EMS\CoreBundle\Repository\UserRepository;
use EMS\CoreBundle\Roles;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;

class UserManager
{
    public const PASSWORD_RETRY_TTL = 7200;
    public const CONFIRMATION_TOKEN_TTL = 86400;
    private const string MAIL_TEMPLATE = '/user/mail.twig';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly MailerService $mailerService,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly AuthTokenRepository $authTokenRepository,
        private readonly string $templateNamespace,
    ) {
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

    public function getUser(): ?User
    {
        try {
            return $this->getAuthenticatedUser();
        } catch (\Throwable) {
            return null;
        }
    }

    public function getUserLanguage(): string
    {
        return $this->getUser()?->getLanguage() ?? User::DEFAULT_LOCALE;
    }

    /**
     * @return array{count: int, results: iterable<User>}
     */
    public function countFindAll(?string $email): array
    {
        return $this->userRepository->countFindAll($email);
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

    public function proxyAuthenticate(string $username, ?string $email): string
    {
        if (!$this->authorizationChecker->isGranted(Roles::ROLE_USER_MANAGEMENT)) {
            throw new AccessDeniedException();
        }

        $user = $email
            ? $this->getUserByEmail($email) ?? $this->getUserByUsername($username)
            : $this->getUserByUsername($username);

        if (!$user instanceof UserInterface) {
            throw new NotFoundException('User not found');
        }

        if ($user->isExpired()) {
            throw new AccountExpiredException(\sprintf('The account "%s" is expired', $user->getUserIdentifier()));
        }
        if (!$user->isEnabled()) {
            throw new DisabledException(\sprintf('The account "%s" is disabled', $user->getUserIdentifier()));
        }

        $this->loginUser($user);

        return $this->authTokenRepository->create($user)->getValue();
    }

    public function loginUser(User $user): void
    {
        $user->setLastLogin(new \DateTime());
        $this->update($user);
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

        $mailTemplate = $this->mailerService->makeMailTemplate("@$this->templateNamespace".self::MAIL_TEMPLATE);
        $mailTemplate
            ->addTo($user->getEmail())
            ->setSubject('user.resetting.email.subject', ['username' => $user->getUsername()], EMSCoreBundle::TRANS_USER_DOMAIN)
            ->setBodyBlock('resetPassword', ['user' => $user])
        ;

        $this->mailerService->sendMailTemplate($mailTemplate, 'text');

        $user->setPasswordRequestedAt(new \DateTime());
        $this->update($user);

        return $user;
    }

    public function resetPassword(User $user): void
    {
        $user->setConfirmationToken(null);
        $user->setPasswordRequestedAt();
        $user->setEnabled(true);
        $this->update($user);

        $this->loginUser($user);
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
        $hashedPassword = $this->userPasswordHasher->hashPassword($user, $plainPassword);

        $user->setPassword($hashedPassword);
    }

    private function getToken(): TokenInterface
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            throw new \RuntimeException('Token is null, could not get the currentUser from token.');
        }

        return $token;
    }
}

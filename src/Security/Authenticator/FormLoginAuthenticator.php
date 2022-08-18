<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Security\Provider\UserProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormLoginAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{
    use TargetPathTrait;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private UserPasswordEncoderInterface $passwordEncoder;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordEncoderInterface $passwordEncoder,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): bool
    {
        return Routes::USER_LOGIN === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    /**
     * @return array{username: ?string, password: ?string, csrf_token: ?string}
     */
    public function getCredentials(Request $request): array
    {
        $credentials = [
            'username' => $request->request->get('_username'),
            'password' => $request->request->get('_password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(Security::LAST_USERNAME, $credentials['username']);

        return $credentials;
    }

    /**
     * @param UserProvider $userProvider
     */
    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        return $userProvider->loadUserByUsername($credentials['username']);
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        if (null === $credentials['password'] || null === $credentials['username']) {
            throw new BadCredentialsException();
        }

        return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
    }

    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): RedirectResponse
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate(Routes::DASHBOARD_HOME));
    }

    protected function getLoginUrl(): string
    {
        return $this->urlGenerator->generate(Routes::USER_LOGIN);
    }
}

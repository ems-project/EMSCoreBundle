<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Ldap;

use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Type;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LdapFormLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly LdapConfig $ldapConfig,
        private readonly LdapUserProvider $ldapUserProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return Routes::USER_LOGIN === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    #[\Override]
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(Routes::USER_LOGIN);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $username = Type::string($request->request->get('_username', ''));
        $password = Type::string($request->request->get('_password', ''));
        $csrfToken = Type::string($request->request->get('_csrf_token', ''));

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username, fn ($userIdentifier) => $this->ldapUserProvider->loadUserByIdentifier($userIdentifier)),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
                new LdapBadge(
                    Ldap::class,
                    $this->ldapConfig->dnString,
                    $this->ldapConfig->searchDn,
                    $this->ldapConfig->searchPassword
                ),
            ]
        );
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate(Routes::DASHBOARD_HOME));
    }
}

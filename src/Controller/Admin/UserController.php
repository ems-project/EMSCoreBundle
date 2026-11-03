<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Core\User\UserOptions;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\ByteString;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly GroupManager $groupManager,
        private readonly WysiwygProfileService $wysiwygProfileService,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $data = Json::decode($request->getContent());
        if (!isset($data['username'], $data['email'])) {
            throw new BadRequestException('Missing "username" or "email" parameter');
        }

        $username = Type::string($data['username']);
        $email = Type::string($data['email']);
        if ('' === $username || '' === $email) {
            throw new BadRequestHttpException('The "username" and "email" parameters are required.');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPlainPassword(ByteString::fromRandom(32)->toString());
        $user->setEmail($email);
        $user->setEnabled(true);

        if (isset($data['wysiwygProfile'])) {
            $profileName = Type::string($data['wysiwygProfile']);
            $profile = $this->wysiwygProfileService->getByItemName($profileName);
            if (!$profile instanceof WysiwygProfile) {
                throw new BadRequestHttpException(\sprintf('WYSIWYG profile "%s" not found.', $profileName));
            }
            $user->setWysiwygProfile($profile);
        }

        if (isset($data['roles'])) {
            $user->setRoles(Type::stringArray($data['roles']));
        }

        if (isset($data['group'])) {
            $groupName = Type::string($data['group']);
            $group = $this->groupManager->getByItemName($groupName);
            if (null === $group) {
                throw new BadRequestHttpException(\sprintf('Group "%s" not found.', $groupName));
            }
            $user->setGroup($group);
        }

        if (isset($data['displayName'])) {
            $user->setDisplayName(Type::string($data['displayName']));
        }
        if (isset($data['locale'])) {
            $user->setLocale(Type::string($data['locale']));
        }
        if (\array_key_exists('localePreferred', $data)) {
            $user->setLocalePreferred(Type::nullableString($data['localePreferred']));
        }
        if (isset($data['userOptions'])) {
            /** @var array{simplified_ui?: bool, allowed_configure_wysiwyg?: bool, custom_options?: mixed} $userOptions */
            $userOptions = Type::array($data['userOptions']);
            $user->setUserOptions(new UserOptions($userOptions));
        }

        $this->userManager->add($user);
        $user = $this->userManager->requestResetPassword($username);
        if (null === $user || null === $user->getConfirmationToken()) {
            throw new BadRequestHttpException('User not found.');
        }
        $token = $user->getConfirmationToken();

        return new JsonResponse([
            'url' => $this->generateUrl('emsco_user_resetting_reset', [
                'token' => $token,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'token' => $token,
        ], JsonResponse::HTTP_CREATED);
    }

    public function delete(Request $request): JsonResponse
    {
        $username = Type::string(Json::decode($request->getContent())['username'] ?? null);
        if ('' === $username) {
            throw new BadRequestHttpException('The "username" parameter is required.');
        }

        $user = $this->userManager->getUserByUsernameOrEmail($username);
        if (null === $user) {
            throw new BadRequestHttpException('User not found.');
        }

        $this->userManager->delete($user);

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function edit(Request $request): JsonResponse
    {
        $data = Json::decode($request->getContent());
        $username = Type::string($data['username'] ?? null);
        if ('' === $username) {
            throw new BadRequestHttpException('The "username" parameter is required.');
        }

        $user = $this->userManager->getUserByUsernameOrEmail($username);
        if (null === $user) {
            throw new BadRequestHttpException('User not found.');
        }

        if (\array_key_exists('email', $data)) {
            $user->setEmail(Type::nullableString($data['email']));
        }
        if (\array_key_exists('wysiwygProfile', $data)) {
            $profileName = Type::nullableString($data['wysiwygProfile']);
            if (null === $profileName) {
                $user->setWysiwygProfile(null);
            } else {
                $profile = $this->wysiwygProfileService->getByItemName($profileName);
                if (!$profile instanceof WysiwygProfile) {
                    throw new BadRequestHttpException(\sprintf('WYSIWYG profile "%s" not found.', $profileName));
                }
                $user->setWysiwygProfile($profile);
            }
        }
        if (\array_key_exists('roles', $data)) {
            $user->setRoles(Type::stringArray($data['roles']));
        }
        if (\array_key_exists('group', $data)) {
            $groupName = Type::nullableString($data['group']);
            if (null === $groupName) {
                $user->setGroup(null);
            } else {
                $group = $this->groupManager->getByItemName($groupName);
                if (null === $group) {
                    throw new BadRequestHttpException(\sprintf('Group "%s" not found.', $groupName));
                }
                $user->setGroup($group);
            }
        }
        if (\array_key_exists('displayName', $data)) {
            $user->setDisplayName(Type::string($data['displayName']));
        }
        if (\array_key_exists('locale', $data)) {
            $user->setLocale(Type::string($data['locale']));
        }
        if (\array_key_exists('localePreferred', $data)) {
            $user->setLocalePreferred(Type::nullableString($data['localePreferred']));
        }
        if (\array_key_exists('userOptions', $data)) {
            /** @var array{simplified_ui?: bool, allowed_configure_wysiwyg?: bool, custom_options?: mixed} $userOptions */
            $userOptions = Type::array($data['userOptions']);
            $user->setUserOptions(new UserOptions($userOptions));
        }

        $this->userManager->update($user);

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function resetPasswordUrl(Request $request): JsonResponse
    {
        $username = Type::string(Json::decode($request->getContent())['username'] ?? null);
        if ('' === $username) {
            throw new BadRequestHttpException('The "username" parameter is required.');
        }

        $user = $this->userManager->requestResetPassword($username);
        if (null === $user || null === $user->getConfirmationToken()) {
            throw new BadRequestHttpException('User not found.');
        }
        $token = $user->getConfirmationToken();

        return new JsonResponse([
            'url' => $this->generateUrl('emsco_user_resetting_reset', [
                'token' => $token,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'token' => $token,
        ]);
    }
}

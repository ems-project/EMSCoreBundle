<?php

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\ContentType\FieldType\FieldTypeService;
use EMS\CoreBundle\Core\ContentType\FieldType\FieldTypeTreeItem;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\DataTable\Type\UserDataTableType;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\UserType;
use EMS\CoreBundle\Repository\AuthTokenRepository;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly UserService $userService,
        private readonly UserManager $userManager,
        private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGenerator,
        private readonly DataTableFactory $dataTableFactory,
        private readonly AuthTokenRepository $authTokenRepository,
        private readonly WysiwygProfileRepository $wysiwygProfileRepository,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly string $templateNamespace,
        private readonly FieldTypeService $fieldTypeService,
        private readonly string $dateTimeFormat,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(UserDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render("@$this->templateNamespace/user/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function contentTypePermissions(): Response
    {
        $contentTypes = $this->contentTypeRepository->findAll();

        $contentTypeCounts = [];
        foreach ($contentTypes as $contentType) {
            $tree = $this->fieldTypeService->getTree($contentType);

            $fieldTypesWithMinimumRole = $tree->getChildrenRecursive()->filter(fn (FieldTypeTreeItem $item) => $item->getFieldType()->getRestrictionOption('minimum_role', false));

            $contentTypeCounts[$contentType->getId()] = \count($fieldTypesWithMinimumRole);

            $roles = [
                'view' => $contentType->getRoles()['view'],
                'create' => $contentType->getRoles()['create'],
                'edit' => $contentType->getRoles()['edit'],
                'publish' => $contentType->getRoles()['publish'],
                'delete' => $contentType->getRoles()['delete'],
                'trash' => $contentType->getRoles()['trash'],
                'archive' => $contentType->getRoles()['archive'],
                'show_link_create' => $contentType->getRoles()['show_link_create'],
                'show_link_search' => $contentType->getRoles()['show_link_search'],
            ];
        }

        $roles = [
            Roles::ROLE_AUTHOR,
            Roles::ROLE_REVIEWER,
            Roles::ROLE_TRADUCTOR,
            Roles::ROLE_AUDITOR,
            Roles::ROLE_COPYWRITER,
            Roles::ROLE_PUBLISHER,
            Roles::ROLE_WEBMASTER,
            Roles::ROLE_ADMIN,
            Roles::ROLE_SUPER_ADMIN,
        ];

        $rolesFunctionality = [
            Roles::ROLE_API,
            Roles::ROLE_FORM_CRM,
            Roles::ROLE_TASK_MANAGER,
            Roles::ROLE_ALLOW_ALIGN,
            Roles::ROLE_USER_MANAGEMENT,
            Roles::ROLE_COPY_PASTE,
            Roles::ROLE_DEFAULT_SEARCH,
            Roles::ROLE_SUPER_USER,
            Roles::ROLE_USER_READ,
        ];

        return $this->render("@$this->templateNamespace/user/permissions/permissions.html.twig", [
            'contentTypeCounts' => $contentTypeCounts,
            'roles' => $roles,
            'rolesFunctionality' => $rolesFunctionality,
            'contentTypes' => $contentTypes,
        ]);
    }

    public function contentTypeFieldsPermissions(ContentType $contentType): Response
    {
        $tree = $this->fieldTypeService->getTree($contentType);

        $fieldTypesWithMinimumRole = $tree->getChildrenRecursive()->filter(fn (FieldTypeTreeItem $item) => $item->getFieldType()->getRestrictionOption('minimum_role', false));

        return $this->render("@$this->templateNamespace/user/permissions/specific-permissions.html.twig", [
            'contentType' => $contentType,
            'tree' => $tree,
            'children' => $fieldTypesWithMinimumRole,
        ]);
    }

    public function addUser(Request $request): Response
    {
        $user = new User();
        $result = $this->wysiwygProfileRepository->findBy([], ['orderKey' => 'asc'], 1);
        if (\count($result) > 0) {
            $user->setWysiwygProfile($result[0]);
        }

        $form = $this->createForm(UserType::class, $user, ['mode' => UserType::MODE_CREATE]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $continue = $this->userExist($user, 'add');

            if ($continue) {
                $user->setEnabled(true);
                $this->userManager->update($user);
                $this->addFlash('notice', 'User created!');

                return $this->redirectToRoute(Routes::USER_INDEX);
            }
        }

        return $this->render("@$this->templateNamespace/user/add.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function editUser(User $id, Request $request): Response
    {
        return $this->edit($id, $request);
    }

    public function edit(User $user, Request $request): Response
    {
        $form = $this->createForm(UserType::class, $user, ['mode' => UserType::MODE_UPDATE]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->updateUser($user);
            $this->logger->notice('log.user.updated', [
                'username_managed' => $user->getUsername(),
                'user_display_name' => $user->getDisplayName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);

            return $this->redirectToRoute(Routes::USER_INDEX);
        }

        return $this->render("@$this->templateNamespace/user/edit.html.twig", [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    public function removeUser(User $id): Response
    {
        return $this->delete($id);
    }

    public function delete(User $user): Response
    {
        $username = $user->getUsername();
        $displayName = $user->getDisplayName();
        $this->userService->deleteUser($user);

        $this->logger->notice('log.user.deleted', [
            'username_managed' => $username,
            'user_display_name' => $displayName,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute(Routes::USER_INDEX);
    }

    public function enabling(User $user): Response
    {
        if ($user->isEnabled()) {
            $user->setEnabled(false);
            $message = 'log.user.disabled';
        } else {
            $user->setEnabled(true);
            $message = 'log.user.enabled';
        }
        $this->userService->updateUser($user);

        $this->logger->notice($message, [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute(Routes::USER_INDEX);
    }

    public function apiKey(string $username): Response
    {
        $user = $this->userService->giveUser($username, false);

        $roles = $user->getRoles();
        if (!\in_array('ROLE_API', $roles)) {
            $this->logger->error('log.user.cannot_request_api_key', [
                'user' => $username,
                'initiator' => $this->userService->getCurrentUser()->getUsername(),
            ]);

            throw new \RuntimeException(\sprintf('The user %s  does not have the permission to use API functionalities.', $username));
        }

        $authToken = new AuthToken($user);
        $this->authTokenRepository->save($authToken);

        // TODO: Hide the key in the logs?
        $this->logger->notice('log.user.api_key', [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            'api_key' => $authToken->getValue(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute(Routes::USER_INDEX);
    }

    public function sidebarCollapse(bool $collapsed): Response
    {
        $user = $this->userService->giveUser($this->userService->getCurrentUser()->getUsername(), false);
        $user->setSidebarCollapse($collapsed);
        $this->userService->updateUser($user);

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
        ]);
    }

    public function spreadsheetExport(string $_format): Response
    {
        $rows = [['username', 'display name', 'notification', 'email', 'enabled', 'last login', 'expiration date', 'roles']];
        foreach ($this->userService->getAll() as $user) {
            $rows[] = [
                $user->getUsername(),
                $user->getDisplayName(),
                $user->getEmailNotification() ? 'Y' : 'N',
                $user->getEmail(),
                $user->isEnabled() ? 'Y' : 'N',
                $user->getLastLogin()?->format($this->dateTimeFormat),
                $user->getExpirationDate()?->format($this->dateTimeFormat),
                \implode(', ', $user->getRoles()),
            ];
        }

        return $this->spreadsheetGenerator->generateSpreadsheet([
            SpreadsheetGeneratorServiceInterface::SHEETS => [[
                'name' => 'Users',
                'rows' => $rows,
            ]],
            SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => 'users',
            SpreadsheetGeneratorServiceInterface::WRITER => $_format,
        ]);
    }

    private function userExist(User $user, string $action): bool
    {
        $exists = [
            'email' => $this->userManager->getUserByEmail($user->getEmail()),
            'username' => $this->userManager->getUserByUsername($user->getUsername()),
        ];
        $messages = ['email' => 'User email already exist!', 'username' => 'Username already exist!'];
        foreach ($exists as $key => $value) {
            if ($value instanceof User) {
                if ('add' === $action || ('edit' === $action && $value->getId() !== $user->getId())) {
                    $this->addFlash('error', $messages[$key]);

                    return false;
                }
            }
        }

        return true;
    }
}

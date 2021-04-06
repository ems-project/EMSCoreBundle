<?php

namespace EMS\CoreBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use EMS\CoreBundle\Service\UserService;
use FOS\UserBundle\Model\UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AppController
{
    /**
     * @var string|null
     */
    private $circleObject;

    private $userService;

    public function __construct(LoggerInterface $logger, FormRegistryInterface $formRegistry, RequestRuntime $requestRuntime, ?string $circleObject, UserService $userService)
    {
        parent::__construct($logger, $formRegistry, $requestRuntime);
        $this->circleObject = $circleObject;
        $this->userService = $userService;
    }

    /**
     * @Route("/user", name="ems.user.index")
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $table = new EntityTable($this->userService);
        $table->addColumn('user.index.column.username', 'username');
        $table->addColumn('user.index.column.displayname', 'name');
        $column = $table->addColumn('user.index.column.email_notification', 'emailNotification', [true => 'fa fa-check-square-o', false => 'fa fa-square-o']);
        $column->setIconClass('fa fa-bell');
        $table->addColumn('user.index.column.email', 'email');
        $createdColumn = $table->addColumn('user.index.column.circles', 'circles');
        $createdColumn->setDataLinks(true);
        $table->addColumn('user.index.column.enabled', 'enabled', [true => 'fa fa-check-square-o', false => 'fa fa-square-o']);
        $createdColumn = $table->addColumn('user.index.column.roles', 'roles');
        $createdColumn->setClass('');
        $createdColumn = $table->addColumn('user.index.column.lastLogin', 'lastLogin');
        $createdColumn->setDateTimeProperty(true);

        $table->addDynamicItemGetAction('user.edit', 'user.action.edit', 'pencil', ['id' => 'id']);
        $table->addDynamicItemGetAction('homepage', 'user.action.switch', 'user-secret', ['_switch_user' => 'username']);
        $table->addDynamicItemPostAction('user.enabling', 'user.action.disable', 'user-times', 'user.action.disable_confirm', ['id' => 'id']);
        $table->addDynamicItemPostAction('EMS_user_apikey', 'user.action.generate_api', 'key', 'user.action.generate_api_confirm', ['username' => 'username']);
        $table->addDynamicItemPostAction('user.delete', 'user.action.delete', 'trash', 'user.action.delete_confirm', ['id' => 'id']);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render('@EMSCore/user/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/user/add", name="user.add")
     *
     * @return Response
     */
    public function addUserAction(Request $request, UserService $userService, UserManagerInterface $userManager)
    {
        $user = new User();

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var WysiwygProfileRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygProfile');
        $result = $repository->findBy([], ['orderKey' => 'asc'], 1);
        if (\count($result) > 0) {
            $user->setWysiwygProfile($result[0]);
        }

        $form = $this->createFormBuilder($user)
            ->add('username', null, ['label' => 'form.username', 'translation_domain' => 'FOSUserBundle'])
            ->add('email', EmailType::class, ['label' => 'form.email', 'translation_domain' => 'FOSUserBundle'])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => ['translation_domain' => 'FOSUserBundle'],
                'first_options' => ['label' => 'form.password'],
                'second_options' => ['label' => 'form.password_confirmation'],
                'invalid_message' => 'fos_user.password.mismatch', ])

            ->add('allowedToConfigureWysiwyg', CheckboxType::class, [
                'required' => false,
            ])
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => 'WYSIWYG profile',
                'class' => 'EMSCoreBundle:WysiwygProfile',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC');
                },
            ])
            ->add('wysiwygOptions', TextareaType::class, [
                'required' => false,
                'label' => 'WYSIWYG custom options',
                'attr' => [
                    'rows' => 8,
                ],
            ]);

        if ($circleObject = $this->circleObject) {
            $form->add('circles', ObjectPickerType::class, [
                'multiple' => true,
                'type' => $circleObject,
                'dynamicLoading' => false,
            ]);
        }

        $form = $form->add('roles', ChoiceType::class, ['choices' => $userService->getExistingRoles(),
            'label' => 'Roles',
            'expanded' => true,
            'multiple' => true,
            'mapped' => true, ])
            ->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-plus',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $continue = $this->userExist($user, 'add', $userManager);

            if ($continue) {
                $user->setEnabled(true);
                $userManager->updateUser($user);
                $this->addFlash(
                    'notice',
                    'User created!'
                );

                return $this->redirectToRoute('ems.user.index');
            }
        }

        return $this->render('@EMSCore/user/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse|Response
     *
     * @Route("/user/{id}/edit", name="user.edit")
     */
    public function editUserAction($id, Request $request, LoggerInterface $logger, UserService $userService)
    {
        $user = $userService->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('username', null, [
                'label' => 'form.username',
                'disabled' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('emailNotification', CheckboxType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('displayName', null, [
                'label' => 'Display name',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('circles', ObjectPickerType::class, [
                'multiple' => true,
                'type' => $this->circleObject,
                'dynamicLoading' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('allowedToConfigureWysiwyg', CheckboxType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('wysiwygProfile', EntityType::class, [
                'required' => false,
                'label' => 'WYSIWYG profile',
                'class' => 'EMSCoreBundle:WysiwygProfile',
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')->orderBy('p.orderKey', 'ASC');
                },
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
                'attr' => [
                    'data-live-search' => true,
                    'class' => 'wysiwyg-profile-picker',
                ],
            ])
            ->add('wysiwygOptions', CodeEditorType::class, [
                'label' => 'WYSIWYG Options',
                'required' => false,
                'language' => 'ace/mode/json',
                'attr' => [
                    'class' => 'wysiwyg-profile-options',
                ],
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('roles', ChoiceType::class, ['choices' => $this->getExistingRoles($userService),
                'label' => 'Roles',
                'expanded' => true,
                'multiple' => true,
                'mapped' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('update', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userService->updateUser($user);
            $logger->notice('log.user.updated', [
                'username_managed' => $user->getUsername(),
                'user_display_name' => $user->getDisplayName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);

            return $this->redirectToRoute('ems.user.index');
        }

        return $this->render('@EMSCore/user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     *
     * @Route("/user/{id}/delete", name="user.delete")
     */
    public function removeUserAction($id, LoggerInterface $logger, UserService $userService)
    {
        $user = $userService->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        $username = $user->getUsername();
        $displayName = $user->getDisplayName();
        $userService->deleteUser($user);
        $this->getDoctrine()->getManager()->flush();

        $logger->notice('log.user.deleted', [
            'username_managed' => $username,
            'user_display_name' => $displayName,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     *
     * @Route("/user/{id}/enabling", name="user.enabling")
     */
    public function enablingUserAction($id, LoggerInterface $logger, UserService $userService)
    {
        $user = $userService->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        if ($user->isEnabled()) {
            $user->setEnabled(false);
            $message = 'log.user.disabled';
        } else {
            $user->setEnabled(true);
            $message = 'log.user.enabled';
        }

        $userService->updateUser($user);
        $this->getDoctrine()->getManager()->flush();

        $logger->notice($message, [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     *
     * @Route("/user/{id}/locking", name="user.locking")
     */
    public function lockingUserAction($id, LoggerInterface $logger, UserService $userService)
    {
        $user = $userService->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        if ($user->isLocked()) {
            $user->setLocked(false);
            $message = 'log.user.unlocked';
        } else {
            $user->setLocked(true);
            $message = 'log.user.locked';
        }

        $userService->updateUser($user);
        $this->getDoctrine()->getManager()->flush();

        $logger->notice($message, [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    /**
     * @param string $username
     *
     * @return RedirectResponse
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/user/{username}/apikey", name="EMS_user_apikey", methods={"POST"})
     */
    public function apiKeyAction($username, LoggerInterface $logger, UserService $userService)
    {
        $user = $userService->getUser($username, false);

        $roles = $user->getRoles();
        if (!\in_array('ROLE_API', $roles)) {
            $logger->error('log.user.cannot_request_api_key', [
                'user' => $username,
                'initiator' => $userService->getCurrentUser()->getUsername(),
            ]);

            throw new \RuntimeException(\sprintf('The user %s  does not have the permission to use API functionalities.', $username));
        }

        $authToken = new AuthToken($user);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($authToken);
        $em->flush();

        //TODO: Hide the key in the logs?
        $logger->notice('log.user.api_key', [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            'api_key' => $authToken->getValue(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    /**
     * @param bool $collapsed
     *
     * @return Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/profile/sidebar-collapse/{collapsed}", name="user.sidebar-collapse", methods={"POST"})
     */
    public function sidebarCollapseAction($collapsed, UserService $userService)
    {
        $user = $userService->getUser($userService->getCurrentUser()->getUsername(), false);
        $user->setSidebarCollapse($collapsed);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => true,
        ]);
    }

    private function getExistingRoles(UserService $userService)
    {
        return $userService->getExistingRoles();
    }

    /**
     * Test if email or username exist return on add or edit Form.
     */
    private function userExist(User $user, string $action, UserManagerInterface $userManager): bool
    {
        $exists = ['email' => $userManager->findUserByEmail($user->getEmail()), 'username' => $userManager->findUserByUsername($user->getUsername())];
        $messages = ['email' => 'User email already exist!', 'username' => 'Username already exist!'];
        foreach ($exists as $key => $value) {
            if ($value instanceof User) {
                if ('add' === $action || ('edit' === $action && $value->getId() !== $user->getId())) {
                    $this->addFlash(
                        'error',
                        $messages[$key]
                    );

                    return false;
                }
            }
        }

        return true;
    }
}

<?php

namespace EMS\CoreBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\DataLinksTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\RolesTableColumn;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use EMS\CoreBundle\Service\UserService;
use FOS\UserBundle\Model\UserManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends AbstractController
{
    private ?string $circleObject;
    private UserService $userService;
    private UserManagerInterface $userManager;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ?string $circleObject, UserService $userService, UserManagerInterface $userManager)
    {
        $this->circleObject = $circleObject;
        $this->logger = $logger;
        $this->userService = $userService;
        $this->userManager = $userManager;
    }

    public function ajaxDataTableAction(Request $request): Response
    {
        $table = $this->initTable();
        $dataTableRequest = DataTableRequest::fromRequest($request);
        $table->resetIterator($dataTableRequest);

        return $this->render('@EMSCore/datatable/ajax.html.twig', [
            'dataTableRequest' => $dataTableRequest,
            'table' => $table,
        ], new JsonResponse());
    }

    public function indexAction(Request $request): Response
    {
        $table = $this->initTable();

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render('@EMSCore/user/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function addUserAction(Request $request): Response
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

        $form = $form->add('roles', ChoiceType::class, ['choices' => $this->userService->getExistingRoles(),
            'label' => 'Roles',
            'expanded' => true,
            'multiple' => true,
            'mapped' => true, ])
            ->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-plus',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $continue = $this->userExist($user, 'add', $this->userManager);

            if ($continue) {
                $user->setEnabled(true);
                $this->userManager->updateUser($user);
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

    public function editUserAction(User $id, Request $request): Response
    {
        return $this->edit($id, $request);
    }

    public function edit(User $user, Request $request): Response
    {
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
            ->add('roles', ChoiceType::class, [
                'choices' => $this->getExistingRoles(),
                'label' => 'Roles',
                'expanded' => true,
                'multiple' => true,
                'mapped' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->add('update', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->updateUser($user);
            $this->logger->notice('log.user.updated', [
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

    public function removeUserAction(User $id): Response
    {
        return $this->delete($id);
    }

    public function delete(User $user): Response
    {
        $username = $user->getUsername();
        $displayName = $user->getDisplayName();
        $this->userService->deleteUser($user);
        $this->getDoctrine()->getManager()->flush();

        $this->logger->notice('log.user.deleted', [
            'username_managed' => $username,
            'user_display_name' => $displayName,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('ems.user.index');
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
        $this->getDoctrine()->getManager()->flush();

        $this->logger->notice($message, [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    public function apiKeyAction(string $username): Response
    {
        return $this->apiKey($username);
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

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($authToken);
        $em->flush();

        //TODO: Hide the key in the logs?
        $this->logger->notice('log.user.api_key', [
            'username_managed' => $user->getUsername(),
            'user_display_name' => $user->getDisplayName(),
            'api_key' => $authToken->getValue(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
        ]);

        return $this->redirectToRoute('ems.user.index');
    }

    public function sidebarCollapseAction(bool $collapsed): Response
    {
        $user = $this->userService->giveUser($this->userService->getCurrentUser()->getUsername(), false);
        $user->setSidebarCollapse($collapsed);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getExistingRoles(): array
    {
        return $this->userService->getExistingRoles();
    }

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

    private function initTable(): EntityTable
    {
        $table = new EntityTable($this->userService, $this->generateUrl('ems_core_user_ajax_data_table'));
        $table->addColumn('user.index.column.username', 'username');
        $table->addColumn('user.index.column.displayname', 'displayName');
        $table->addColumnDefinition(new BoolTableColumn('user.index.column.email_notification', 'emailNotification'))
            ->setIconClass('fa fa-bell');
        $table->addColumn('user.index.column.email', 'email');
        $table->addColumnDefinition(new DataLinksTableColumn('user.index.column.circles', 'circles'));
        $table->addColumnDefinition(new BoolTableColumn('user.index.column.enabled', 'enabled'));
        $table->addColumnDefinition(new RolesTableColumn('user.index.column.roles', 'roles'));
        $table->addColumnDefinition(new DatetimeTableColumn('user.index.column.lastLogin', 'lastLogin'));

        $table->addDynamicItemGetAction('user.edit', 'user.action.edit', 'pencil', ['id' => 'id']);
        $table->addDynamicItemGetAction('homepage', 'user.action.switch', 'user-secret', ['_switch_user' => 'username']);
        $table->addDynamicItemPostAction('user.enabling', 'user.action.disable', 'user-times', 'user.action.disable_confirm', ['id' => 'id']);
        $table->addDynamicItemPostAction('EMS_user_apikey', 'user.action.generate_api', 'key', 'user.action.generate_api_confirm', ['username' => 'username']);
        $table->addDynamicItemPostAction('user.delete', 'user.action.delete', 'trash', 'user.action.delete_confirm', ['id' => 'id']);

        return $table;
    }
}

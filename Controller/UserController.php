<?php

namespace EMS\CoreBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AppController
{
    /**
     * @Route("/user", name="ems.user.index"))
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('@EMSCore/user/index.html.twig', [
            'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:User', 'ems.user.index', 'username'),
        ]);
    }


    /**
     * @param int $id
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     *
     * @Route("/user/{id}/edit", name="user.edit")
     */
    public function editUserAction($id, Request $request, LoggerInterface $logger)
    {
        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }


        $form = $this->createFormBuilder($user)
            ->add('email', EmailType::class, array(
                'label' => 'form.email',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ))
            ->add('username', null, array(
                'label' => 'form.username',
                'disabled' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
            ))
            ->add('emailNotification', CheckboxType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
            ])
            ->add('displayName', null, array(
                'label' => 'Display name',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
            ))
            ->add('circles', ObjectPickerType::class, [
                'multiple' => true,
                'type' => $this->container->getParameter('ems_core.circles_object'),
                'dynamicLoading' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN

            ])
            ->add('enabled', CheckboxType::class, [
                'required' => false,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
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
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
            ])
            ->add('roles', ChoiceType::class, array('choices' => $this->getExistingRoles(),
                'label' => 'Roles',
                'expanded' => true,
                'multiple' => true,
                'mapped' => true,
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN
            ))
            ->add('update', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save',
                'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getUserService()->updateUser($user);
            $logger->notice('log.user.updated', [
                'username_managed' => $user->getUsername(),
                'user_display_name' => $user->getDisplayName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);
            return $this->redirectToRoute('ems.user.index');
        }

        return $this->render('@EMSCore/user/edit.html.twig', array(
            'form' => $form->createView(),
            'user' => $user
        ));
    }

    /**
     * @param int $id
     * @param LoggerInterface $logger
     * @return RedirectResponse
     *
     * @Route("/user/{id}/delete", name="user.delete")
     */
    public function removeUserAction($id, LoggerInterface $logger)
    {
        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        $username = $user->getUsername();
        $displayName = $user->getDisplayName();
        $this->getUserService()->deleteUser($user);
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
     * @param LoggerInterface $logger
     * @return RedirectResponse
     *
     * @Route("/user/{id}/enabling", name="user.enabling")
     */
    public function enablingUserAction($id, LoggerInterface $logger)
    {

        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        if ($user->isEnabled()) {
            $user->setEnabled(false);
            $message = "log.user.disabled";
        } else {
            $user->setEnabled(true);
            $message = "log.user.enabled";
        }

        $this->getUserService()->updateUser($user);
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
     * @param LoggerInterface $logger
     * @return RedirectResponse
     *
     * @Route("/user/{id}/locking", name="user.locking")
     */
    public function lockingUserAction($id, LoggerInterface $logger)
    {

        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }

        if ($user->isLocked()) {
            $user->setLocked(false);
            $message = "log.user.unlocked";
        } else {
            $user->setLocked(true);
            $message = "log.user.locked";
        }

        $this->getUserService()->updateUser($user);
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
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/user/{username}/apikey", name="EMS_user_apikey", methods={"POST"})
     */
    public function apiKeyAction($username, LoggerInterface $logger)
    {
        $user = $this->getUserService()->getUser($username, false);

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
     * @return Response
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/profile/sidebar-collapse/{collapsed}", name="user.sidebar-collapse", methods={"POST"})
     */
    public function sidebarCollapseAction($collapsed)
    {
        $user = $this->getUserService()->getUser($this->getUserService()->getCurrentUser()->getUsername(), false);
        $user->setSidebarCollapse($collapsed);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        return $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => true,
        ]);
    }

    private function getExistingRoles()
    {
        return $this->getUserService()->getExistingRoles();
    }
}

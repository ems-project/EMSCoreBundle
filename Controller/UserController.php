<?php
namespace EMS\CoreBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;

class UserController extends AppController
{
    /**
     * @Route("/user", name="ems.user.index"))
     */
    public function indexAction(Request $request)
    {
        return $this->render('@EMSCore/user/index.html.twig', [
                'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:User', 'ems.user.index', 'username'),
        ]);
    }
    
    
    
    /**
     *
     * @Route("/user/{id}/edit", name="user.edit")
     */
    public function editUserAction($id, Request $request)
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
//         ->add('locked')
//         ->add('expiresAt', DateType::class, array(
//                 'required' => FALSE,
//                    'widget' => 'single_text',
//                 'format' => 'd/M/y',
//                  'html5' => FALSE,
//                 'attr' => array('class' => 'datepicker',
//                      'data-date-format' => 'dd/mm/yyyy',
//                     'data-today-highlight' => FALSE,
//                     'data-week-start' => 1,
//                     'data-days-of-week-highlighted' => true,
//                     'data-days-of-week-disabled' => false,
//                     'data-multidate' => FALSE,
                    
//                 ),
//         ))
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
        ->add('wysiwygOptions',CodeEditorType::class, [
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
            $this->addFlash(
                'notice',
                'User was modified!'
            );
            return $this->redirectToRoute('ems.user.index');
        }
    
        return $this->render('@EMSCore/user/edit.html.twig', array(
                'form' => $form->createView(),
                'user' => $user
        ));
    }
    
    /**
     *
     * @Route("/user/{id}/delete", name="user.delete")
     */
    public function removeUserAction($id, Request $request)
    {
    
        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }
        
        $this->getUserService()->deleteUser($user);
        $this->getDoctrine()->getManager()->flush();
        $this->addFlash(
            'notice',
            'User was deleted!'
        );
        return $this->redirectToRoute('ems.user.index');
    }
    
    /**
     *
     * @Route("/user/{id}/enabling", name="user.enabling")
     */
    public function enablingUserAction($id, Request $request)
    {
    
        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }
        
        $message = "User was ";
        if ($user->isEnabled()) {
            $user->setEnabled(false);
            $message = $message . "disabled !";
        } else {
            $user->setEnabled(true);
            $message = $message . "enabled !";
        }
        
        $this->getUserService()->updateUser($user);
        $this->getDoctrine()->getManager()->flush();
        $this->addFlash(
            'notice',
            $message
        );
        return $this->redirectToRoute('ems.user.index');
    }
    
    /**
     *
     * @Route("/user/{id}/locking", name="user.locking")
     */
    public function lockingUserAction($id, Request $request)
    {
    
        $user = $this->getUserService()->getUserById($id);
        // test if user exist before modified it
        if (!$user) {
            throw $this->createNotFoundException('user not found');
        }
        $message = "User was ";
        if ($user-> isLocked()) {
            $user->setLocked(false);
            $message = $message . "unlocked !";
        } else {
            $user->setLocked(true);
            $message = $message . "locked !";
        }
        
        $this->getUserService()->updateUser($user);
        $this->getDoctrine()->getManager()->flush();
        $this->addFlash(
            'notice',
            $message
        );
        return $this->redirectToRoute('ems.user.index');
    }
    
    /**
     *
     * @Route("/user/{username}/apikey", name="EMS_user_apikey")
     * @Method({"POST"})
     */
    public function apiKeyAction($username, Request $request)
    {
        $user = $this->getUserService()->getUser($username, false);
        
        $authToken = new AuthToken($user);

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($authToken);
        $em->flush();
        
        $this->addFlash('notice', 'Here is a new API key for user '.$user->getUsername().' '.$authToken->getValue());
        
        return $this->redirectToRoute('ems.user.index');
    }
    
    /**
     *
     * @Route("/profile/sidebar-collapse/{collapsed}", name="user.sidebar-collapse")
     * @Method({"POST"})
     */
    public function sidebarCollapseAction($collapsed, Request $request)
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
    
    /**
     * Test if email or username exist return on add or edit Form
     */
    private function userExist($user, $action, $form)
    {
        $exists = array('email' => $this->getUserService()->findUserByEmail($user->getEmail()), 'username' => $this->getUserService()->getUser($user->getUsername()));
        $messages = array('email' => 'User email already exist!', 'username' => 'Username already exist!');
        foreach ($exists as $key => $value) {
            if ($value instanceof User) {
                if ($action == 'add' or ($action == 'edit' and $value->getId() != $user->getId())) {
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
    
    private function getExistingRoles()
    {
        return $this->getUserService()->getExistingRoles();
    }
}

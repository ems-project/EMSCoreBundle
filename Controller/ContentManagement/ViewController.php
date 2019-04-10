<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ViewController extends AppController
{
    /**
     * @Route("/view/{type}", name="view.index")
     */
    public function indexAction($type, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        
        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);
            
        if (!$contentTypes || count($contentTypes) != 1) {
            throw new NotFoundHttpException('Content type not found');
        }
        
        
        return $this->render('@EMSCore/view/index.html.twig', [
                'contentType' => $contentTypes[0]
        ]);
    }
    
    /**
     * @Route("/view/add/{type}", name="view.add")
     */
    public function addAction($type, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository('EMSCoreBundle:ContentType');
        
        $contentTypes = $contentTypeRepository->findBy([
            'deleted' => false,
            'name' => $type,
        ]);
            
        if (!$contentTypes || count($contentTypes) != 1) {
            throw new NotFoundHttpException('Content type not found');
        }
        
        $view = new View();
        $view->setContentType($contentTypes[0]);
        
        $form = $this->createForm(ViewType::class, $view);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($view);
            $em->flush();
            
            $this->addFlash('notice', 'A new view has been created');
            
            return $this->redirectToRoute('view.edit', [
                    'id' => $view->getId()
            ]);
        }
        
        return $this->render('@EMSCore/view/add.html.twig', [
                'contentType' => $contentTypes[0],
                'form' => $form->createView()
        ]);
    }
    
    /**
     * @Route("/view/edit/{id}.{_format}", name="view.edit", defaults={"_format": "html"})
     */
    public function editAction($id, $_format, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');
        
        /** @var View $view **/
        $view = $viewRepository->find($id);
            
        if (!$view) {
            throw new NotFoundHttpException('View type not found');
        }
        
        $form = $this->createFormBuilder($view)
        ->add('name', IconTextType::class, [
                'icon' => 'fa fa-tag'
        ])
        ->add('public', CheckboxType::class, [
            'required' => false,
        ])
        ->add('icon', IconPickerType::class, [
                'required' => false,
        ])
        ->add('options', get_class($this->get($view->getType())), [
                'view' => $view,
        ])
        ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm',
                    'data-ajax-save-url' => $this->generateUrl('view.edit', ['id' => $id, '_format' => 'json']),
                ],
                'icon' => 'fa fa-save'
        ])->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($view);
            $em->flush();
        }
        
        return $this->render('@EMSCore/view/edit.'.$_format.'.twig', [
                'form' => $form->createView(),
                'view' => $view
        ]);
    }
    
    /**
     * @Route("/view/remove/{id}", name="view.remove")
     * @Method({"POST"})
     */
    public function removeAction($id, Request $request)
    {
        
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');
        
        /** @var View $view **/
        $view = $viewRepository->find($id);
            
        if (!$view) {
            throw new NotFoundHttpException('View not found');
        }
        
        $em->remove($view);
        $em->flush();

        $this->addFlash('notice', 'A view has been removed');
            
        return $this->redirectToRoute('view.index', [
                'type' => $view->getContentType()->getName()
        ]);
    }
}

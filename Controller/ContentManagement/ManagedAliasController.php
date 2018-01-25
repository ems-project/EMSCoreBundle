<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Form\ManagedAliasType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/environment/managed-alias")
 */
class ManagedAliasController extends AppController
{
    /**
     * @param Request $request
     * 
     * @Route("/add", name="environment_add_managed_alias")
     */
    public function addAction(Request $request)
    {
        $managedAlias = new ManagedAlias();
        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));

            $this->addFlash('notice', sprintf('Managed alias %s has been created', $managedAlias->getName()));
            return $this->redirectToRoute('environment.index');
        }

        return $this->render('EMSCoreBundle:environment:managed_alias.html.twig', [
            'new' => true,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * @param Request $request
     * @param string  $id
     *
     * @Route("/edit/{id}", requirements={"id": "\d+"}, name="environment_edit_managed_alias")
     */
    public function editAction(Request $request, $id)
    {
        $managedAlias = $this->getAliasService()->getManagedAlias($id);
        
        if (!$managedAlias) {
            throw new NotFoundHttpException('Unknow managed alias');
        }
        
        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));
            
            $this->addFlash('notice', sprintf('Managed alias %s has been updated', $managedAlias->getName()));
            
            return $this->redirectToRoute('environment.index');
        }
        
        return $this->render('EMSCoreBundle:environment:managed_alias.html.twig', [
            'new' => false,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * @param string $id
     *
     * @Route("/remove/{id}", requirements={"id": "\d+"}, name="environment_remove_managed_alias")
     * @Method({"POST"})
     */
    public function removeAction($id)
    {
        $managedAlias = $this->getAliasService()->getManagedAlias($id);
        
        if ($managedAlias) {
            $this->getAliasService()->removeAlias($managedAlias->getAlias());
            
            /* @var $em EntityManager */
            $em = $this->getDoctrine()->getManager();
            $em->remove($managedAlias);
            $em->flush();
            
            $this->addFlash('notice', sprintf('The managed %s has been removed', $managedAlias->getName()));
        }
        
        return $this->redirectToRoute ( 'environment.index' );
    }
    
    /**
     * @param ManagedAlias $managedAlias
     * @param array        $actions
     */
    private function save(ManagedAlias $managedAlias, array $actions)
    {
        $managedAlias->setAlias($this->getParameter('ems_core.instance_id'));
        $this->getAliasService()->updateAlias($managedAlias->getAlias(), $actions);

        /* @var $em EntityManager */
        $em = $this->getDoctrine()->getManager();
        $em->persist($managedAlias);
        $em->flush();
    }
    
    /**
     * @param Form $form
     *
     * @return array
     */
    private function getIndexActions(Form $form)
    {
        $actions = [];
        $submitted = $form->get('indexes')->getData();
        $indexes = array_keys($form->getConfig()->getOption('indexes'));
        
        if (empty($submitted)) {
            return $actions;
        }
        
        foreach ($indexes as $index) {
            if (in_array($index, $submitted)) {
                $actions['add'][] = $index;
            } else {
                $actions['remove'][] = $index;
            }
        }
        
        return $actions;
    }
}
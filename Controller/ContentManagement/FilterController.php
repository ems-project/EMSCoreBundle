<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Form\FilterType;


/**
 * @Route("/filter")
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class FilterController extends AppController
{
	/**
	 * @Route("/", name="ems_filter_index")
	 */
	public function indexAction(Request $request) {
		return $this->render( '@EMSCore/filter/index.html.twig', [
				'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:Filter', 'ems_filter_index', 'name'),
		] );
	}
	
	/**
	 * Edit a filter entity.
	 *
	 * @Route("/edit/{filter}", name="ems_filter_edit")
	 * @Method({"GET", "POST"})
	 */
	public function editAction(Filter $filter, Request $request)
	{
		
		$form = $this->createForm(FilterType::class, $filter);
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			$filter =  $form->getData();
			$em->persist($filter);
			$em->flush($filter);
			
			return $this->redirectToRoute ( 'ems_filter_index', [
			] );
		}
		
		return $this->render( '@EMSCore/filter/edit.html.twig', [
				'form' => $form->createView (),
		] );
	}
	
	/**
	 * Creates a new filter entity.
	 *
	 * @Route("/delete/{filter}", name="ems_filter_delete")
	 * @Method({"POST"})
	 */
	public function deleteAction(Filter $filter, Request $request) {
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		$em->remove($filter);
		$em->flush();
		
		$this->addFlash('notice', 'The filter has been deleted');
		return $this->redirectToRoute ( 'ems_filter_index', [
		] );
	}
	
	/**
	 * Creates a new elasticsearch filter entity.
	 *
	 * @Route("/add", name="ems_filter_add")
	 * @Method({"GET", "POST"})
	 */
	public function addAction(Request $request)
	{
		$filter = new Filter();
		$form= $this->createForm(FilterType::class, $filter);
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			$filter=  $form->getData();
			$em->persist($filter);
			$em->flush($filter);
			
			return $this->redirectToRoute ( 'ems_filter_index', [
			] );
		}
		
		return $this->render( '@EMSCore/filter/add.html.twig', [
				'form' => $form->createView (),
		] );
		
	}
}
	
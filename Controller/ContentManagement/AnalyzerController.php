<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\DataField\ChoiceFieldType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Form\Form\AnalyzerType;


/**
 * @Route("/analyzer")
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class AnalyzerController extends AppController
{
	/**
	 * @Route("/", name="ems_analyzer_index")
	 */
	public function indexAction(Request $request) {
		return $this->render( 'EMSCoreBundle:analyzer:index.html.twig', [
				'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:Analyzer', 'ems_analyzer_index', 'name'),
		] );
	}
	
	/**
	 * Edit an analyzer entity.
	 *
	 * @Route("/edit/{analyzer}", name="ems_analyzer_edit")
	 * @Method({"GET", "POST"})
	 */
	public function editAction(Analyzer $analyzer, Request $request)
	{
		
		$form= $this->createForm(AnalyzerType::class, $analyzer);
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			$analyzer =  $form->getData();
			$analyzer->setOptions([]);
			
			$em->persist($analyzer);
			$em->flush($analyzer);
			
			return $this->redirectToRoute ( 'ems_analyzer_index', [
			] );
		}
		
		return $this->render( 'EMSCoreBundle:analyzer:edit.html.twig', [
				'form' => $form->createView (),
		] );
	}
		
	/**
	 * Creates a new elasticsearch analyzer entity.
	 *
	 * @Route("/add", name="ems_analyzer_add")
	 * @Method({"GET", "POST"})
	 */
	public function addAction(Request $request)
	{
		$analyzer = new Analyzer();
		$form= $this->createForm(AnalyzerType::class, $analyzer);
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			$analyzer =  $form->getData();
			$analyzer->setOptions([]);
			
			$em->persist($analyzer);
			$em->flush($analyzer);
			
			return $this->redirectToRoute ( 'ems_analyzer_index', [
			] );
		}
		
		return $this->render( 'EMSCoreBundle:analyzer:add.html.twig', [
				'form' => $form->createView (),
		] );
		
	}
}
	
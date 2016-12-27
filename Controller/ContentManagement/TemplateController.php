<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Form\Form\TemplateType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\TemplateRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class TemplateController extends AppController
{
	/**
	 * @Route("/template/{type}", name="template.index")
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
			
		if(!$contentTypes || count($contentTypes) != 1) {
			throw new NotFoundHttpException('Content type not found');
		}
		
		
		return $this->render( 'EMSCoreBundle:template:index.html.twig', [
				'contentType' => $contentTypes[0]
		]);
		
		
	}
	
	/**
	 * @Route("/template/add/{type}", name="template.add")
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
			
		if(!$contentTypes || count($contentTypes) != 1) {
			throw new NotFoundHttpException('Content type not found');
		}
		
		$template = new Template();
		$template->setContentType($contentTypes[0]);
		
		$form = $this->createForm ( TemplateType::class, $template );
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			$em->persist($template);
			$em->flush();
			
			$this->addFlash('notice', 'A new template has been created');
			
			return $this->redirectToRoute('template.index', [
					'type' => $type
			]);
		}
		
		return $this->render( 'EMSCoreBundle:template:add.html.twig', [
				'contentType' => $contentTypes[0],
				'form' => $form->createView()
		]);
		
		
	}
	
	/**
	 * @Route("/template/edit/{id}", name="template.edit")
	 */
	public function editAction($id, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var TemplateRepository $templateRepository */
		$templateRepository = $em->getRepository('EMSCoreBundle:Template');
		
		/** @var Template $template **/
		$template = $templateRepository->find($id);
			
		if(!$template) {
			throw new NotFoundHttpException('Template type not found');
		}
		
		$form = $this->createForm ( TemplateType::class, $template );
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			$em->persist($template);
			$em->flush();

			$this->addFlash('notice', 'A template has been updated');
			
			return $this->redirectToRoute('template.index', [
					'type' => $template->getContentType()->getName()
			]);
		}
		
		return $this->render( 'EMSCoreBundle:template:edit.html.twig', [
				'form' => $form->createView(),
				'template' => $template
		]);
	}
	
	/**
	 * @Route("/template/remove/{id}", name="template.remove")
     * @Method({"POST"})
	 */
	public function removeAction($id, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var TemplateRepository $templateRepository */
		$templateRepository = $em->getRepository('EMSCoreBundle:Template');
		
		/** @var Template $template **/
		$template = $templateRepository->find($id);
			
		if(!$template) {
			throw new NotFoundHttpException('Template type not found');
		}
		
		$em->remove($template);
		$em->flush();
		$this->addFlash('notice', 'A template has been removed');		
			
		return $this->redirectToRoute('template.index', [
				'type' => $template->getContentType()->getName()
		]);
	}
}
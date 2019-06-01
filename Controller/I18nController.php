<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Form\Form\I18nType;
use EMS\CoreBundle\Service\I18nService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use EMS\CoreBundle\Entity\Form\I18nFilter;
use EMS\CoreBundle\Form\Form\I18nFormType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * I18n controller.
 *
 * @Route("/i18n")
 */
class I18nController extends Controller
{
    /**
     * Lists all I18n entities.
     * @param Request $request
     * @return Response
     *
     * @Route("/", name="i18n_index", methods={"GET"})
     */
    public function indexAction(Request $request)
    {
        $filters = $request->query->get('i18n_form');
         
        $i18nFilter = new I18nFilter();
        
         $form = $this->createForm(I18nFormType::class, $i18nFilter, [
                 'method' => 'GET'
         ]);
         $form->handleRequest($request);
         
        if ($form->isSubmitted()) {
            $form->getData();
        }
        
        $count = $this->getI18nService()->count($filters);
        // for pagination
        $paging_size = $this->getParameter('ems_core.paging_size');
        $lastPage = ceil($count/$paging_size);
        $page = $request->query->get('page', 1);
        
        $i18ns = $this->getI18nService()->findAll(($page-1)*$paging_size, $paging_size, $filters);
        
        return $this->render('@EMSCore/i18n/index.html.twig', array(
            'i18nkeys' => $i18ns,
            'lastPage' => $lastPage,
            'paginationPath' => 'i18n_index',
            'filterform' => $form->createView(),
            'page' => $page,
            'paging_size' => $paging_size,
        ));
    }
    
    /**
     * @return I18nService
     */
    private function getI18nService()
    {
        return $this->get('ems.service.i18n');
    }

    /**
     * Creates a new I18n entity.
     * @param Request $request
     * @return RedirectResponse|Response
     *
     * @Route("/new", name="i18n_new", methods={"GET","POST"})
     */
    public function newAction(Request $request)
    {
        $i18n = new I18n();
        $i18n->getContent();
        
        $i18n->setContent(array(array('locale' => "", 'text' => "")));
        
        $form = $this->createForm(I18nType::class, $i18n);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($i18n);
            $em->flush();

            return $this->redirectToRoute('i18n_index', array('id' => $i18n->getId()));
        }

        return $this->render('@EMSCore/i18n/new.html.twig', array(
            'i18n' => $i18n,
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing I18n entity.
     * @param Request $request
     * @param I18n $i18n
     * @return RedirectResponse|Response
     *
     * @Route("/{id}/edit", name="i18n_edit", methods={"GET", "POST"})
     */
    public function editAction(Request $request, I18n $i18n)
    {
//         $deleteForm = $this->createDeleteForm($i18n);
        if (empty($i18n->getContent())) {
            $i18n->setContent([
                [
                    'locale' => '',
                    'text' => '',
                ]
            ]);
        }
        $editForm = $this->createForm(I18nType::class, $i18n);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            //renumber array elements
            $i18n->setContent(array_values($i18n->getContent()));
            $em->persist($i18n);
            $em->flush();

            return $this->redirectToRoute('i18n_index');
        }

        return $this->render('@EMSCore/i18n/edit.html.twig', array(
            'i18n' => $i18n,
            'edit_form' => $editForm->createView(),
        ));
    }

    /**
     * Deletes a I18n entity
     * @param I18n $i18n
     * @return RedirectResponse
     *
     * @Route("/{id}", name="i18n_delete", methods={"POST"})
     */
    public function deleteAction(I18n $i18n)
    {
        $this->getI18nService()->delete($i18n);

        return $this->redirectToRoute('i18n_index');
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Form\FilterType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/filter")
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class FilterController extends AppController
{
    /**
     * @return Response
     *
     * @Route("/", name="ems_filter_index")
     */
    public function indexAction()
    {
        return $this->render('@EMSCore/filter/index.html.twig', [
                'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:Filter', 'ems_filter_index', 'name'),
        ]);
    }

    /**
     * Edit a filter entity.
     *
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/edit/{filter}", name="ems_filter_edit", methods={"GET", "POST"})
     */
    public function editAction(Filter $filter, Request $request)
    {
        $form = $this->createForm(FilterType::class, $filter);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $filter = $form->getData();
            $em->persist($filter);
            $em->flush($filter);

            return $this->redirectToRoute('ems_filter_index', [
            ]);
        }

        return $this->render('@EMSCore/filter/edit.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    /**
     * Creates a new filter entity.
     *
     * @return RedirectResponse
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/delete/{filter}", name="ems_filter_delete", methods={"POST"})
     */
    public function deleteAction(Filter $filter)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->remove($filter);
        $em->flush();

        $this->getLogger()->notice('log.filter.deleted', [
            'filter_name' => $filter->getName(),
        ]);

        return $this->redirectToRoute('ems_filter_index', [
        ]);
    }

    /**
     * Creates a new elasticsearch filter entity.
     *
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     *
     * @Route("/add", name="ems_filter_add", methods={"GET", "POST"})
     */
    public function addAction(Request $request)
    {
        $filter = new Filter();
        $form = $this->createForm(FilterType::class, $filter);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $filter = $form->getData();
            $em->persist($filter);
            $em->flush($filter);

            return $this->redirectToRoute('ems_filter_index', [
            ]);
        }

        return $this->render('@EMSCore/filter/add.html.twig', [
                'form' => $form->createView(),
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Form\Form\FilterType;
use EMS\CoreBundle\Service\HelperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FilterController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger, private readonly HelperService $helperService, private readonly string $templateNamespace)
    {
    }

    public function indexAction(): Response
    {
        return $this->render("@$this->templateNamespace/filter/index.html.twig", [
                'paging' => $this->helperService->getPagingTool(Filter::class, 'ems_filter_index', 'name'),
        ]);
    }

    public function editAction(Filter $filter, Request $request): Response
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

        return $this->render("@$this->templateNamespace/filter/edit.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function deleteAction(Filter $filter): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->remove($filter);
        $em->flush();

        $this->logger->notice('log.filter.deleted', [
            'filter_name' => $filter->getName(),
        ]);

        return $this->redirectToRoute('ems_filter_index', [
        ]);
    }

    public function addAction(Request $request): Response
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

        return $this->render("@$this->templateNamespace/filter/add.html.twig", [
                'form' => $form->createView(),
        ]);
    }

    public function export(Filter $filter): Response
    {
        $response = new JsonResponse($filter);
        $response->setEncodingOptions(JSON_PRETTY_PRINT);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filter->getName().'.json'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}

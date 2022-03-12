<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Form\AnalyzerType;
use EMS\CoreBundle\Service\HelperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/analyzer")
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class AnalyzerController extends AbstractController
{
    private HelperService $helperService;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, HelperService $helperService)
    {
        $this->helperService = $helperService;
        $this->logger = $logger;
    }

    /**
     * @Route("/", name="ems_analyzer_index")
     */
    public function indexAction(): Response
    {
        return $this->render('@EMSCore/analyzer/index.html.twig', [
                'paging' => $this->helperService->getPagingTool('EMSCoreBundle:Analyzer', 'ems_analyzer_index', 'name'),
        ]);
    }

    /**
     * Edit an analyzer entity.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/edit/{analyzer}", name="ems_analyzer_edit", methods={"GET", "POST"})
     */
    public function editAction(Analyzer $analyzer, Request $request): Response
    {
        $form = $this->createForm(AnalyzerType::class, $analyzer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $analyzer = $form->getData();
            $em->persist($analyzer);
            $em->flush($analyzer);

            $this->logger->notice('log.analyzer.updated', [
                'analyzer_name' => $analyzer->getName(),
                'analyzer_id' => $analyzer->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);

            return $this->redirectToRoute('ems_analyzer_index', [
            ]);
        }

        return $this->render('@EMSCore/analyzer/edit.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    /**
     * Creates a new elasticsearch analyzer entity.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/delete/{analyzer}", name="ems_analyzer_delete", methods={"POST"})
     */
    public function deleteAction(Analyzer $analyzer): RedirectResponse
    {
        $id = $analyzer->getId();
        $name = $analyzer->getName();

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->remove($analyzer);
        $em->flush();

        $this->logger->notice('log.analyzer.deleted', [
            'analyzer_name' => $name,
            'analyzer_id' => $id,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('ems_analyzer_index', [
        ]);
    }

    /**
     * Creates a new elasticsearch analyzer entity.
     *
     * @return RedirectResponse|Response
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @Route("/add", name="ems_analyzer_add", methods={"GET", "POST"})
     */
    public function addAction(Request $request): Response
    {
        $analyzer = new Analyzer();
        $form = $this->createForm(AnalyzerType::class, $analyzer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();
            $analyzer = $form->getData();
            if ($analyzer instanceof Analyzer) {
                $em->persist($analyzer);
                $em->flush($analyzer);

                $this->logger->notice('log.analyzer.created', [
                    'analyzer_name' => $analyzer->getName(),
                    'analyzer_id' => $analyzer->getId(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                ]);

                return $this->redirectToRoute('ems_analyzer_index', [
                ]);
            }
        }

        return $this->render('@EMSCore/analyzer/add.html.twig', [
                'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/export/{analyzer}.json", name="emsco_analyzer_export")
     */
    public function export(Analyzer $analyzer): Response
    {
        $response = new JsonResponse($analyzer);
        $response->setEncodingOptions(JSON_PRETTY_PRINT);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $analyzer->getName().'.json'
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}

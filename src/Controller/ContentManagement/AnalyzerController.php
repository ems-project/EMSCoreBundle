<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Analyzer;
use EMS\CoreBundle\Form\Form\AnalyzerType;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Service\HelperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AnalyzerController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HelperService $helperService,
        private readonly AnalyzerRepository $analyzerRepository,
        private readonly string $templateNamespace
    ) {
    }

    public function index(): Response
    {
        return $this->render("@$this->templateNamespace/analyzer/index.html.twig", [
            'paging' => $this->helperService->getPagingTool(Analyzer::class, 'ems_analyzer_index', 'name'),
        ]);
    }

    public function edit(Analyzer $analyzer, Request $request): Response
    {
        $form = $this->createForm(AnalyzerType::class, $analyzer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $analyzer = $form->getData();
            $this->analyzerRepository->update($analyzer);

            $this->logger->notice('log.analyzer.updated', [
                'analyzer_name' => $analyzer->getName(),
                'analyzer_id' => $analyzer->getId(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
            ]);

            return $this->redirectToRoute('ems_analyzer_index', [
            ]);
        }

        return $this->render("@$this->templateNamespace/analyzer/edit.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function delete(Analyzer $analyzer): RedirectResponse
    {
        $id = $analyzer->getId();
        $name = $analyzer->getName();
        $this->analyzerRepository->delete($analyzer);

        $this->logger->notice('log.analyzer.deleted', [
            'analyzer_name' => $name,
            'analyzer_id' => $id,
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_DELETE,
        ]);

        return $this->redirectToRoute('ems_analyzer_index', [
        ]);
    }

    public function add(Request $request): Response
    {
        $analyzer = new Analyzer();
        $form = $this->createForm(AnalyzerType::class, $analyzer);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $analyzer = $form->getData();
            if ($analyzer instanceof Analyzer) {
                $this->analyzerRepository->update($analyzer);

                $this->logger->notice('log.analyzer.created', [
                    'analyzer_name' => $analyzer->getName(),
                    'analyzer_id' => $analyzer->getId(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                ]);

                return $this->redirectToRoute('ems_analyzer_index', [
                ]);
            }
        }

        return $this->render("@$this->templateNamespace/analyzer/add.html.twig", [
            'form' => $form->createView(),
        ]);
    }

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

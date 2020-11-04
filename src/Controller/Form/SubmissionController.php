<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Form;

use EMS\CoreBundle\Form\Submission\ProcessType;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SubmissionController extends AbstractController
{
    /** @var FormSubmissionService */
    private $formSubmissionService;
    /** @var TranslatorInterface */
    private $translator;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        FormSubmissionService $formSubmissionService,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->formSubmissionService = $formSubmissionService;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * @Route("/form/submissions", name="form.submissions", methods={"GET"})
     */
    public function indexAction(): Response
    {
        $processForm = $this->createForm(ProcessType::class, []);

        return $this->render('@EMSCore/submission/overview.html.twig', [
            'submissions' => $this->formSubmissionService->getUnprocessed(),
            'formProcess' => $processForm->createView(),
        ]);
    }

    /**
     * @Route("/form/submissions/process", name="form.submissions.process",  methods={"POST"})
     */
    public function process(Request $request, UserInterface $user): RedirectResponse
    {
        $processForm = $this->createForm(ProcessType::class, []);
        $processForm->handleRequest($request);

        if (!$processForm->isSubmitted() || !$processForm->isValid()) {
            $this->addFlash('error', $this->translator->trans('form_submissions.process.error', [], 'EMSCoreBundle'));

            return $this->redirectToRoute('form.submissions');
        }

        $formSubmission = $this->formSubmissionService->get($processForm->get('submissionId')->getData());
        $this->formSubmissionService->process($formSubmission, $user);

        $this->addFlash('notice', $this->translator->trans('form_submissions.process.success', ['%id%' => $formSubmission->getId()], 'EMSCoreBundle'));

        return $this->redirectToRoute('form.submissions');
    }

    /**
     * @Route("/form/submissions/download/{id}", name="form.submissions.download", requirements={"id": "\S+"}, methods={"GET"})
     */
    public function download(string $id): Response
    {
        try {
            $formSubmission = $this->formSubmissionService->get($id);
            $download = $this->formSubmissionService->createDownload($formSubmission);

            $response = new BinaryFileResponse($download);
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                sprintf('%s.zip', $formSubmission->getId())
            ));

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('form.submissions');
        }
    }
}

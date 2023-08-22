<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Form;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\FormSubmissionDataTableType;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Service\Form\Submission\FormSubmissionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\User\UserInterface;

final class SubmissionController extends AbstractController
{
    public function __construct(
        private readonly FormSubmissionService $formSubmissionService,
        private readonly LoggerInterface $logger,
        private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGeneratorService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace
    ) {
    }

    public function indexAction(Request $request, UserInterface $user): Response
    {
        $table = $this->dataTableFactory->create(FormSubmissionDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case TableAbstract::DELETE_ACTION:
                        $this->formSubmissionService->processByIds($table->getSelected(), $user);
                        break;
                    case TableAbstract::DOWNLOAD_ACTION:
                        return $this->downloadMultiple($table->getSelected());
                    case TableAbstract::EXPORT_ACTION:
                        $config = $config = $this->formSubmissionService->generateExportConfig($table->getSelected());

                        return $this->spreadsheetGeneratorService->generateSpreadsheet($config);
                    default:
                        $this->logger->error('log.controller.action.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.action.unknown_action');
            }

            return $this->redirectToRoute('form.submissions');
        }

        return $this->render("@$this->templateNamespace/form-submission/index.html.twig", [
            'form' => $form->createView(),
        ]);
    }

    public function process(Request $request, UserInterface $user): RedirectResponse
    {
        $this->formSubmissionService->process($request->get('formSubmission'), $user);

        return $this->redirectToRoute('form.submissions');
    }

    public function download(string $formSubmission): Response
    {
        try {
            $response = $this->formSubmissionService->createDownload($formSubmission);

            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                \sprintf('%s.zip', $formSubmission)
            ));

            return $response;
        } catch (\Exception) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('form.submissions');
        }
    }

    /**
     * @param array<string> $submissionIds
     */
    private function downloadMultiple(array $submissionIds): Response
    {
        try {
            $response = $this->formSubmissionService->createDownloadForMultiple($submissionIds);
        } catch (\Exception) {
            $this->addFlash('error', 'error');

            return $this->redirectToRoute('form.submissions');
        }

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'submissions.zip')
        );

        return $response;
    }
}

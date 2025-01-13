<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision\Action;

use EMS\CommonBundle\Contracts\SpreadsheetGeneratorServiceInterface;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Service\Pdf\Pdf;
use EMS\CommonBundle\Service\Pdf\PdfPrinterInterface;
use EMS\CommonBundle\Service\Pdf\PdfPrintOptions;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment as Twig;

class ActionController
{
    public function __construct(
        private readonly TemplateRepository $templateRepository,
        private readonly EnvironmentService $environmentService,
        private readonly SearchService $searchService,
        private readonly PdfPrinterInterface $pdfPrinter,
        private readonly SpreadsheetGeneratorServiceInterface $spreadsheetGenerator,
        private readonly LoggerInterface $logger,
        private readonly Twig $twig,
        private readonly string $templateNamespace,
    ) {
    }

    public function render(
        string $environmentName,
        int $templateId,
        string $ouuid,
        bool $_download,
        bool $public
    ): Response {
        $action = $this->templateRepository->getById($templateId);
        if ($public && !$action->isPublic()) {
            throw new NotFoundHttpException('Template type not found');
        }

        $environment = $this->environmentService->giveByName($environmentName);
        $document = $this->searchService->getDocument($action->giveContentType(), $ouuid, $environment);

        $body = $this->twig->createTemplate($action->getBody());

        if ($_download || !$action->getPreview()
            && \in_array($action->getRenderOption(), [RenderOptionType::PDF, RenderOptionType::EXPORT])) {
            try {
                $content = $body->render([
                    'environment' => $environment,
                    'contentType' => $action->getContentType(),
                    'object' => $document,
                    'source' => $document->getSource(),
                    '_download' => $_download,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
                $content = 'Error in template';
            }
            $filename = $this->generateFilename($action, $environment, $document, $_download);

            return match ($action->getRenderOption()) {
                RenderOptionType::PDF => $this->generatePdfResponse($action, $filename, $content),
                RenderOptionType::EXPORT => $this->generateExportResponse($action, $filename, $content),
                default => throw new \Exception('Render options not supported'),
            };
        }

        return new Response($this->twig->render("@$this->templateNamespace/data/custom-view.html.twig", [
            'template' => $action,
            'environment' => $environment,
            'contentType' => $action->getContentType(),
            'object' => $document,
            'source' => $document->getSource(),
            '_download' => true,
            'body' => $body,
        ]));
    }

    private function generatePdfResponse(Template $action, string $filename, string $content): Response
    {
        $pdf = new Pdf($filename, $content);
        $printOptions = new PdfPrintOptions([
            PdfPrintOptions::ATTACHMENT => PdfPrintOptions::ATTACHMENT === $action->getDisposition(),
            PdfPrintOptions::COMPRESS => true,
            PdfPrintOptions::HTML5_PARSING => true,
            PdfPrintOptions::ORIENTATION => $action->getOrientation() ?? 'portrait',
            PdfPrintOptions::SIZE => $action->getSize() ?? 'A4',
        ]);

        return $this->pdfPrinter->getStreamedResponse($pdf, $printOptions);
    }

    private function generateExportResponse(Template $action, string $filename, string $content): Response
    {
        if ($action->isSpreadsheet()) {
            $response = $this->spreadsheetGenerator->generateSpreadsheet([
                SpreadsheetGeneratorServiceInterface::SHEETS => Json::decode($content),
                SpreadsheetGeneratorServiceInterface::CONTENT_FILENAME => $filename,
                SpreadsheetGeneratorServiceInterface::WRITER => $action->getExtension(),
            ]);
        } else {
            $response = new Response($content);
            if (null !== $action->getMimeType()) {
                $response->headers->set('Content-Type', $action->getMimeType());
            }
        }

        if (null !== $action->getDisposition()) {
            $response->headers->set(
                'Content-Disposition',
                HeaderUtils::makeDisposition($action->getDisposition(), $filename.'.'.($action->getExtension() ?? ''))
            );
        }
        if (null != $action->getAllowOrigin()) {
            $response->headers->set('Access-Control-Allow-Origin', $action->getAllowOrigin());
            $response->headers->set(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, Accept, Accept-Language, If-None-Match, If-Modified-Since'
            );
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
        }

        return $response;
    }

    private function generateFilename(Template $action, Environment $environment, DocumentInterface $document, bool $_download): string
    {
        $template = $action->getFilename();
        $template ??= (RenderOptionType::PDF === $action->getRenderOption() ? 'document.pdf' : $document->getOuuid());

        $twigTemplate = $this->twig->createTemplate($template);

        try {
            $filename = $twigTemplate->render([
                'environment' => $environment,
                'contentType' => $action->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
                '_download' => $_download,
            ]);
            $filename = \preg_replace('~[\r\n]+~', '', $filename);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        return $filename ?? 'error-in-filename-template';
    }
}

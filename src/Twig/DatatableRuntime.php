<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Service\DatatableService;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

final class DatatableRuntime implements RuntimeExtensionInterface
{
    public function __construct(private readonly DatatableService $datatableService, private readonly Environment $twig)
    {
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $options): string
    {
        $datatable = $this->datatableService->generateDatatable($environmentNames, $contentTypeNames, $options);
        $template = $this->twig->load('@EMSCore/datatable/dom.html.twig');

        return $this->twig->render($template, [
            'datatable' => $datatable,
        ]);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getExcelPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        return $this->datatableService->getExcelPath($environmentNames, $contentTypeNames, $options);
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public function getCsvPath(array $environmentNames, array $contentTypeNames, array $options): string
    {
        return $this->datatableService->getCsvPath($environmentNames, $contentTypeNames, $options);
    }
}

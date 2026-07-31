<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetDataTableType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Roles;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;
use Twig\TemplateWrapper;

class BrowseController
{
    public function __construct(
        private readonly DataTableFactory $dataTableFactory,
        private readonly FormFactory $formFactory,
        private readonly Environment $twig,
        private readonly DashboardManager $dashboardManager,
        private readonly string $templateNamespace,
    ) {
    }

    public function modalUploadedFiles(): JsonResponse
    {
        $table = $this->dataTableFactory->create(UploadedAssetDataTableType::class, [
            'location' => UploadedAssetDataTableType::LOCATION_FILE_MODAL,
            'roles' => [Roles::ROLE_USER],
        ]);
        $form = $this->formFactory->create(TableType::class, $table);

        return new JsonResponse([
            'content' => $this->getTemplate()->renderBlock('modalUploadedFiles', ['form' => $form->createView()]),
        ]);
    }

    public function modalDashboard(string $dashboardName): JsonResponse
    {
        $dashboard = $this->dashboardManager->getByName($dashboardName);

        return new JsonResponse([
            'content' => $this->getTemplate()->renderBlock('modalDashboard', ['dashboard' => $dashboard]),
        ]);
    }

    private function getTemplate(): TemplateWrapper
    {
        return $this->twig->load(\sprintf('@%s/modal/browse.twig', $this->templateNamespace));
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetAdminDataTableType;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetDataTableType;
use EMS\CoreBundle\Entity\UploadedAsset;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\FileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class UploadedFileController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly FileService $fileService,
        private readonly DataTableFactory $dataTableFactory,
    ) {
    }

    public function adminOverview(Request $request): Page|Response
    {
        $table = $this->dataTableFactory->create(UploadedAssetAdminDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fileIds = $table->getSelected();

            if (TableAbstract::DOWNLOAD_ACTION === $this->getClickedButtonName($form)) {
                return $this->fileService->createDownloadForMultiple(fileIds: $fileIds);
            }

            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->fileService->deleteByIds($fileIds),
                UploadedAssetAdminDataTableType::TOGGLE_VISIBILITY_ACTION => $this->fileService->toggleFileEntitiesVisibility($fileIds),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::UPLOAD_ASSET_ADMIN_OVERVIEW);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'uploaded-files-logs'],
            'icon' => 'fa fa-upload',
            'title' => t('key.uploaded_files_logs', [], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->add(
                label: t('key.uploaded_files', [], 'emsco-core'),
                icon: 'fa fa-upload',
                route: Routes::UPLOAD_ASSET_ADMIN_OVERVIEW,
            ),
        ]);
    }

    public function adminDelete(UploadedAsset $uploadedAsset): Response
    {
        $this->fileService->delete($uploadedAsset);

        return $this->redirectToRoute(Routes::UPLOAD_ASSET_ADMIN_OVERVIEW);
    }

    public function adminToggleVisibility(string $assetId): Response
    {
        $this->fileService->toggleFileEntitiesVisibility([$assetId]);

        return $this->redirectToRoute(Routes::UPLOAD_ASSET_ADMIN_OVERVIEW);
    }

    public function publisherIndex(Request $request): Page|Response
    {
        $table = $this->dataTableFactory->create(UploadedAssetDataTableType::class, [
            'location' => UploadedAssetDataTableType::LOCATION_PUBLISHER_OVERVIEW,
            'roles' => [Roles::ROLE_PUBLISHER],
        ]);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (TableAbstract::DOWNLOAD_ACTION === $this->getClickedButtonName($form)) {
                return $this->fileService->createDownloadForMultiple(
                    fileIds: $this->fileService->hashesToIds($table->getSelected())
                );
            }

            match ($this->getClickedButtonName($form)) {
                UploadedAssetDataTableType::HIDE_ACTION => $this->fileService->hideByHashes($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::UPLOAD_ASSET_PUBLISHER_OVERVIEW);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'uploaded-files'],
            'icon' => 'fa fa-upload',
            'title' => t('key.uploaded_files', [], 'emsco-core'),
            'breadcrumb' => Navigation::publishers()->add(
                label: t('key.uploaded_files', [], 'emsco-core'),
                icon: 'fa fa-upload',
                route: Routes::UPLOAD_ASSET_PUBLISHER_OVERVIEW,
            ),
        ]);
    }

    public function publisherHideByHash(string $hash): Response
    {
        $this->fileService->hideByHashes([$hash]);

        return $this->redirectToRoute(Routes::UPLOAD_ASSET_PUBLISHER_OVERVIEW);
    }
}

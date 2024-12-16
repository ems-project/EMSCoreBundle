<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\AjaxService;
use EMS\CoreBundle\DataTable\Type\UploadedAsset\UploadedAssetDataTableType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Roles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UploadedFileWysiwygController extends AbstractController
{
    public function __construct(
        private readonly AjaxService $ajax,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Response
    {
        $table = $this->dataTableFactory->create(UploadedAssetDataTableType::class, [
            'location' => UploadedAssetDataTableType::LOCATION_WYSIWYG_BROWSER,
            'roles' => [Roles::ROLE_USER],
        ]);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        return $this->render("@$this->templateNamespace/uploaded-file-wysiwyg/index.html.twig", [
            'form' => $form->createView(),
            'CKEditorFuncNum' => $request->query->get('CKEditorFuncNum') ?: 0,
        ]);
    }

    public function modal(): Response
    {
        $table = $this->dataTableFactory->create(UploadedAssetDataTableType::class, [
            'location' => UploadedAssetDataTableType::LOCATION_FILE_MODAL,
            'roles' => [Roles::ROLE_USER],
        ]);
        $form = $this->createForm(TableType::class, $table);

        return $this->ajax->newAjaxModel("@$this->templateNamespace/uploaded-file-wysiwyg/modal.html.twig")
            ->setBody('modalBody', ['form' => $form->createView()])
            ->getResponse();
    }
}

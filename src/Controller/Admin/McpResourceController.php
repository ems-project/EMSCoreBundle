<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\McpResourceDataTableType;
use EMS\CoreBundle\Entity\McpResource;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\McpResourceType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mcp\McpResourceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class McpResourceController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly McpResourceService $mcpResourceService,
        private readonly DataTableFactory $dataTableFactory,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(McpResourceDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->mcpResourceService->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::MCP_RESOURCE_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'mcp-resources'],
            'icon' => 'fa fa-plug',
            'title' => t('type.title_overview', ['type' => 'mcp_resource'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_resource'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $mcpResource = new McpResource();
        $form = $this->createForm(McpResourceType::class, $mcpResource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpResourceService->update($mcpResource);

            return $this->redirectToRoute(Routes::MCP_RESOURCE_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'mcp_resource'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_resource'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'mcp_resource'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, McpResource $mcpResource): Page|RedirectResponse
    {
        $form = $this->createForm(McpResourceType::class, $mcpResource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpResourceService->update($mcpResource);

            return $this->redirectToRoute(Routes::MCP_RESOURCE_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'mcp_resource', 'label' => $mcpResource->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_resource'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'mcp_resource', 'label' => $mcpResource->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function delete(McpResource $mcpResource): Response
    {
        $this->mcpResourceService->delete($mcpResource);

        return $this->redirectToRoute(Routes::MCP_RESOURCE_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.mcp_resources', [], 'emsco-core'),
            icon: 'fa fa-file-code-o',
            route: Routes::MCP_RESOURCE_INDEX,
        );
    }
}

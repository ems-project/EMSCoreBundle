<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\McpToolDataTableType;
use EMS\CoreBundle\Entity\McpTool;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\McpToolType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mcp\McpToolService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class McpToolController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly McpToolService $mcpToolService,
        private readonly DataTableFactory $dataTableFactory,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(McpToolDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->mcpToolService->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::MCP_TOOL_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'mcp-tools'],
            'icon' => 'fa fa-plug',
            'title' => t('type.title_overview', ['type' => 'mcp_tool'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_tool'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $mcpTool = new McpTool();
        $form = $this->createForm(McpToolType::class, $mcpTool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpToolService->update($mcpTool);

            return $this->redirectToRoute(Routes::MCP_TOOL_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'mcp_tool'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_tool'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'mcp_tool'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, McpTool $mcpTool): Page|RedirectResponse
    {
        $form = $this->createForm(McpToolType::class, $mcpTool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpToolService->update($mcpTool);

            return $this->redirectToRoute(Routes::MCP_TOOL_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'mcp_tool', 'label' => $mcpTool->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_tool'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'mcp_tool', 'label' => $mcpTool->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function delete(McpTool $mcpTool): Response
    {
        $this->mcpToolService->delete($mcpTool);

        return $this->redirectToRoute(Routes::MCP_TOOL_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.mcp_tools', [], 'emsco-core'),
            icon: 'fa fa-plug',
            route: Routes::MCP_TOOL_INDEX,
        );
    }
}

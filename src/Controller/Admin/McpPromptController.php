<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\McpPromptDataTableType;
use EMS\CoreBundle\Entity\McpPrompt;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\McpPromptType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Mcp\McpPromptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class McpPromptController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly McpPromptService $mcpPromptService,
        private readonly DataTableFactory $dataTableFactory,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(McpPromptDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->mcpPromptService->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::MCP_PROMPT_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'mcp-prompts'],
            'icon' => 'fa fa-commenting-o',
            'title' => t('type.title_overview', ['type' => 'mcp_prompt'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_prompt'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $mcpPrompt = new McpPrompt();
        $form = $this->createForm(McpPromptType::class, $mcpPrompt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpPromptService->update($mcpPrompt);

            return $this->redirectToRoute(Routes::MCP_PROMPT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'mcp_prompt'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_prompt'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'mcp_prompt'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, McpPrompt $mcpPrompt): Page|RedirectResponse
    {
        $form = $this->createForm(McpPromptType::class, $mcpPrompt);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->mcpPromptService->update($mcpPrompt);

            return $this->redirectToRoute(Routes::MCP_PROMPT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'mcp_prompt', 'label' => $mcpPrompt->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'mcp_prompt'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'mcp_prompt', 'label' => $mcpPrompt->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function delete(McpPrompt $mcpPrompt): Response
    {
        $this->mcpPromptService->delete($mcpPrompt);

        return $this->redirectToRoute(Routes::MCP_PROMPT_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.mcp_prompts', [], 'emsco-core'),
            icon: 'fa fa-commenting-o',
            route: Routes::MCP_PROMPT_INDEX,
        );
    }
}

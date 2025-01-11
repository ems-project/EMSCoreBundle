<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Revision\Task\DataTable\TasksDataTableContext;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionTasksDataTableType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Form\TableType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final readonly class RevisionTask implements DashboardInterface
{
    public function __construct(
        private Environment $twig,
        private RequestStack $requestStack,
        private FormFactoryInterface $formFactory,
        private TaskManager $taskManager,
        private DataTableFactory $dataTableFactory,
        private string $templateNamespace,
    ) {
    }

    #[\Override]
    public function getResponse(Dashboard $dashboard): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $tab = $request?->get('tab', TasksDataTableContext::TAB_USER);
        $tabs = $this->getDashboardTabs();

        if (!\in_array($tab, $tabs, true)) {
            throw new NotFoundHttpException(\sprintf('Could not find tab %s', $tab));
        }

        $table = $this->dataTableFactory->create(RevisionTasksDataTableType::class, ['tab' => $tab]);
        $form = $this->formFactory->create(TableType::class, $table);
        $form->handleRequest($request);

        return new Response($this->twig->render("@$this->templateNamespace/revision/task/dashboard.html.twig", \array_filter([
            'table' => $table,
            'formTable' => $form->createView(),
            'currentTab' => $tab,
            'tabs' => $tabs,
            'filterForm' => $table->getFilterForm()?->createView(),
            'loadMaxRows' => RevisionTasksDataTableType::LOAD_MAX_ROWS,
        ])));
    }

    /**
     * @return string[]
     */
    private function getDashboardTabs(): array
    {
        return \array_filter([
            TasksDataTableContext::TAB_USER,
            TasksDataTableContext::TAB_REQUESTER,
            $this->taskManager->isTaskManager() ? TasksDataTableContext::TAB_MANAGER : null,
        ]);
    }
}

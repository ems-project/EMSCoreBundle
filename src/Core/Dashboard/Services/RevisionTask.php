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
use Symfony\Component\Translation\TranslatableMessage;
use Twig\Environment;

use function Symfony\Component\Translation\t;

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
        $tab = $request?->query->getString('tab', TasksDataTableContext::TAB_USER);
        $tabs = $this->getDashboardTabs();

        if (null === $tab || !\array_key_exists($tab, $tabs)) {
            throw new NotFoundHttpException(\sprintf('Could not find tab %s', $tab));
        }

        $table = $this->dataTableFactory->create(RevisionTasksDataTableType::class, ['tab' => $tab]);
        $form = $this->formFactory->create(TableType::class, $table);
        $form->handleRequest($request);

        return new Response($this->twig->render(\sprintf('@%s/revision/task/dashboard.html.twig', $this->templateNamespace), \array_filter([
            'table' => $table,
            'formTable' => $form->createView(),
            'currentTab' => $tab,
            'tabs' => $tabs,
            'filterForm' => $table->getFilterForm()?->createView(),
            'loadMaxRows' => RevisionTasksDataTableType::LOAD_MAX_ROWS,
        ])));
    }

    /**
     * @return array<string, array{label: TranslatableMessage}>
     */
    private function getDashboardTabs(): array
    {
        $tabs = [
            TasksDataTableContext::TAB_USER => ['label' => t('task.dashboard.tab.user', [], 'emsco-core')],
            TasksDataTableContext::TAB_REQUESTER => ['label' => t('task.dashboard.tab.requester', [], 'emsco-core')],
        ];

        if ($this->taskManager->isTaskManager()) {
            $tabs[TasksDataTableContext::TAB_MANAGER] = ['label' => t('task.dashboard.tab.manager', [], 'emsco-core')];
        }

        return $tabs;
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Revision\Task\Table\TaskTableFilters;
use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Revision\Task\RevisionTaskFiltersType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class RevisionTask extends AbstractType implements DashboardInterface
{
    public function __construct(private readonly Environment $twig, private readonly RouterInterface $router, private readonly RequestStack $requestStack, private readonly FormFactoryInterface $formFactory, private readonly TaskManager $taskManager)
    {
    }

    public function getResponse(Dashboard $dashboard): Response
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $tab = $request->get('tab', 'user');
        $tabs = $this->taskManager->getDashboardTabs();

        if (!\in_array($tab, $tabs, true)) {
            throw new NotFoundHttpException(\sprintf('Could not find tab %s', $tab));
        }

        $filters = new TaskTableFilters();
        $formFilters = $this->formFactory->create(RevisionTaskFiltersType::class, $filters, ['tab' => $tab]);
        $formFilters->handleRequest($request);
        $queryFilter = $request->query->all(RevisionTaskFiltersType::NAME);

        $tableUrl = $this->router->generate('ems_core_task_ajax_datatable', [
            'tab' => $tab,
            RevisionTaskFiltersType::NAME => $queryFilter,
        ]);
        $table = $this->taskManager->getTable($tableUrl, $tab, $filters, false);

        $form = $this->formFactory->create(TableType::class, $table);
        $form->handleRequest($request);

        return new Response($this->twig->render('@EMSCore/revision/task/dashboard.html.twig', \array_filter([
            'table' => $table,
            'formTable' => $form->createView(),
            'currentTab' => $tab,
            'tabs' => $tabs,
            'filterForm' => $table->count() > 0 || \count($queryFilter) > 0 ? $formFilters->createView() : null,
        ])));
    }
}

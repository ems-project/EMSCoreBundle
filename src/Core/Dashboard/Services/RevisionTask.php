<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CoreBundle\Core\Revision\Task\TaskManager;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Form\Form\TableType;
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
    private Environment $twig;
    private RouterInterface $router;
    private RequestStack $requestStack;
    private FormFactoryInterface $formFactory;
    private TaskManager $taskManager;

    public function __construct(
        Environment $twig,
        RouterInterface $router,
        RequestStack $requestStack,
        FormFactoryInterface $formFactory,
        TaskManager $taskManager)
    {
        $this->twig = $twig;
        $this->router = $router;
        $this->requestStack = $requestStack;
        $this->formFactory = $formFactory;
        $this->taskManager = $taskManager;
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

        $tableUrl = $this->router->generate('ems_core_task_ajax_datatable', ['tab' => $tab]);
        $table = $this->taskManager->getTable($tableUrl, $tab, false);

        $form = $this->formFactory->create(TableType::class, $table);
        $form->handleRequest($request);

        return new Response($this->twig->render('@EMSCore/revision/task/dashboard.html.twig', [
            'table' => $table,
            'formTable' => $form->createView(),
            'currentTab' => $tab,
            'tabs' => $tabs,
        ]));
    }
}

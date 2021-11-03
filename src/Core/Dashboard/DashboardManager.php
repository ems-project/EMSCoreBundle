<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard;

use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Repository\DashboardRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\EntityServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class DashboardManager implements EntityServiceInterface
{
    private DashboardRepository $dashboardRepository;
    private LoggerInterface $logger;
    private RouterInterface $router;
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(DashboardRepository $dashboardRepository, LoggerInterface $logger, RouterInterface $router, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->dashboardRepository = $dashboardRepository;
        $this->logger = $logger;
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function isSortable(): bool
    {
        return true;
    }

    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->dashboardRepository->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    public function getEntityName(): string
    {
        return 'dashboard';
    }

    public function count(string $searchValue = '', $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected not null context');
        }

        return $this->dashboardRepository->counter($searchValue);
    }

    public function update(Dashboard $dashboard): void
    {
        if (0 === $dashboard->getOrderKey()) {
            $dashboard->setOrderKey($this->dashboardRepository->counter() + 1);
        }
        $encoder = new Encoder();
        $name = $dashboard->getName();
        $webalized = $encoder->webalize($name);
        if (null === $webalized) {
            throw new \RuntimeException('Unexpected null webalized name');
        }
        $dashboard->setName($webalized);
        $this->dashboardRepository->create($dashboard);
    }

    /**
     * @param string[] $ids
     */
    public function reorderByIds(array $ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $channel = $this->dashboardRepository->getById($id);
            $channel->setOrderKey($counter++);
            $this->dashboardRepository->create($channel);
        }
    }

    /**
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        foreach ($this->dashboardRepository->getByIds($ids) as $dashboard) {
            $this->delete($dashboard);
        }
    }

    public function delete(Dashboard $dashboard): void
    {
        $name = $dashboard->getName();
        $this->dashboardRepository->delete($dashboard);
        $this->logger->warning('log.service.dashboard.delete', [
            'name' => $name,
        ]);
    }

    public function getSidebarMenu(): Menu
    {
        $menu = new Menu();
        foreach ($this->dashboardRepository->getSidebarMenu() as $dashboard) {
            if (!$this->authorizationChecker->isGranted($dashboard->getRole())) {
                continue;
            }
            $menu->addChild($dashboard->getLabel(), $dashboard->getIcon(), $this->router->generate(Routes::DASHBOARD, ['name' => $dashboard->getName()]), $dashboard->getColor());
        }

        return $menu;
    }

    public function getByName(string $name): Dashboard
    {
        $dashboard = $this->dashboardRepository->getByName($name);
        if (null === $dashboard) {
            throw new NotFoundHttpException('Dashboard not found');
        }

        return $dashboard;
    }

    public function getNotificationMenu(): Menu
    {
        $menu = new Menu();
        foreach ($this->dashboardRepository->getNotificationMenu() as $dashboard) {
            if (!$this->authorizationChecker->isGranted($dashboard->getRole())) {
                continue;
            }
            $menu->addChild($dashboard->getLabel(), $dashboard->getIcon(), $this->router->generate(Routes::DASHBOARD, ['name' => $dashboard->getName()]), $dashboard->getColor());
        }

        return $menu;
    }

    public function setQuickSearch(Dashboard $dashboard): void
    {
        if ($dashboard->isQuickSearch()) {
            return;
        }
        $quickSearch = $this->dashboardRepository->getQuickSearch();
        if (null !== $quickSearch) {
            $quickSearch->setQuickSearch(false);
            $this->update($quickSearch);
        }
        $dashboard->setQuickSearch(true);
        $this->update($dashboard);
    }

    public function setLandingPage(Dashboard $dashboard): void
    {
        if ($dashboard->isLandingPage()) {
            return;
        }
        $landingPage = $this->dashboardRepository->getLandingPage();
        if (null !== $landingPage) {
            $landingPage->setLandingPage(false);
            $this->update($landingPage);
        }
        $dashboard->setLandingPage(true);
        $this->update($dashboard);
    }

    public function getLandingPage(): ?Dashboard
    {
        return $this->dashboardRepository->getLandingPage();
    }

    public function getQuickSearch(): ?Dashboard
    {
        return $this->dashboardRepository->getQuickSearch();
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Core\User\GroupManager;
use EMS\CoreBundle\DataTable\Type\GroupDataTableType;
use EMS\CoreBundle\DataTable\Type\UserDataTableType;
use EMS\CoreBundle\Entity\Group;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\GroupType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\UserType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class GroupController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly GroupManager $groupManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(GroupDataTableType::class);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'group'], 'emsco-core'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->groupManager->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::GROUP_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'group'],
            'title' => t('type.title_overview', ['type' => 'group'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'group'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function addGroup(Request $request): Response
    {
        $group = new Group();

        $form = $this->createForm(GroupType::class, $group, ['mode' => UserType::MODE_CREATE]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->groupManager->save($group);

            return $this->redirectToRoute(Routes::GROUP_INDEX);
        }

        return $this->render(\sprintf('@%s/group/create.html.twig', $this->templateNamespace), [
            'form' => $form,
            'title' => t('type.title_create', ['type' => 'group'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'group'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(t('type.title_create', ['type' => 'group'], 'emsco-core')),
        ]);
    }

    public function deleteGroup(Group $group): Response
    {
        $this->groupManager->delete($group);

        return $this->redirectToRoute(Routes::GROUP_INDEX);
    }

    public function editGroup(Group $group, Request $request): Response
    {
        $form = $this->createForm(GroupType::class, $group, [
            'mode' => UserType::MODE_UPDATE,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->groupManager->save($group);

            return $this->redirectToRoute(Routes::GROUP_INDEX);
        }

        $userNotInGroupDataTable = $this->usersInGroupDataTable($group, false);
        $userGroupDataTable = $this->usersInGroupDataTable($group, true);

        return $this->render(\sprintf('@%s/group/edit.html.twig', $this->templateNamespace), [
            'form' => $form,
            'datatableForm' => $userGroupDataTable->createView(),
            'userNotInGroupDataTable' => $userNotInGroupDataTable->createView(),
            'title' => t('type.title_edit', ['type' => 'group', 'label' => $group->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'group'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(t('type.title_edit', ['type' => 'group', 'label' => $group->getLabel()], 'emsco-core')),
        ]);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.users', [], 'emsco-core'),
            icon: 'fa fa-user',
            route: 'emsco_user_index'
        )->add(
            label: t('key.groups', [], 'emsco-core'),
            icon: 'fa fa-users',
            route: 'emsco_group_admin_index'
        );
    }

    /**
     * @return FormInterface<UserDataTableType>
     */
    private function usersInGroupDataTable(Group $group, bool $inGroup): FormInterface
    {
        $table = $this->dataTableFactory->create(UserDataTableType::class, [
            'light' => true,
            'in-group' => $inGroup,
            'group-id' => $group->getId(),
        ]);

        return $this->createForm(TableType::class, $table);
    }
}

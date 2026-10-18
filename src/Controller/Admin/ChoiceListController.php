<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\ChoiceList\ChoiceListManager;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\DataTable\Type\ChoiceListDataTableType;
use EMS\CoreBundle\Entity\ChoiceList;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ChoiceList\ChoiceListType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class ChoiceListController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly ChoiceListManager $choiceListManager,
        private readonly DataTableFactory $dataTableFactory,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(ChoiceListDataTableType::class);

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->choiceListManager->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('message.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::CHOICE_LIST_ADMIN_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView(), 'table_id' => 'choice-lists'],
            'icon' => 'fa fa-list',
            'title' => t('type.title_overview', ['type' => 'choice_list'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'choice_list'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $choiceList = new ChoiceList();
        $form = $this->createForm(ChoiceListType::class, $choiceList, ['create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->choiceListManager->update($choiceList);

            return $this->redirectToRoute(Routes::CHOICE_LIST_ADMIN_EDIT, ['choiceList' => $choiceList->getId()]);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_create', ['type' => 'choice_list'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'choice_list'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_create', ['type' => 'choice_list'], 'emsco-core')
            ),
        ]);
    }

    public function edit(Request $request, ChoiceList $choiceList): Page|RedirectResponse
    {
        $form = $this->createForm(ChoiceListType::class, $choiceList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->choiceListManager->update($choiceList);

            return $this->redirectToRoute(Routes::CHOICE_LIST_ADMIN_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('type.title_edit', ['type' => 'choice_list', 'label' => $choiceList->getLabel()], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'choice_list'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb()->add(
                t('type.title_edit', ['type' => 'choice_list', 'label' => $choiceList->getLabel()], 'emsco-core')
            ),
        ]);
    }

    public function delete(ChoiceList $choiceList): Response
    {
        $this->choiceListManager->delete($choiceList);

        return $this->redirectToRoute(Routes::CHOICE_LIST_ADMIN_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.choice_lists', [], 'emsco-core'),
            icon: 'fa fa-list',
            route: 'emsco_choice_list_admin_index',
        );
    }
}

<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Form\Data\TranslationTableColumn;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Helper\DataTableRequest;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ViewController extends AbstractController
{
    private ContentTypeService $contentTypeService;
    private ViewManager $viewManager;
    private LoggerInterface $logger;

    public function __construct(ContentTypeService $contentTypeService, ViewManager $viewManager, LoggerInterface $logger)
    {
        $this->contentTypeService = $contentTypeService;
        $this->viewManager = $viewManager;
        $this->logger = $logger;
    }

    /**
     * @deprecated
     */
    public function indexDeprecated(string $type, string $_format, Request $request): Response
    {
        @\trigger_error(\sprintf('Route view.index is deprecated, use %s instead', Routes::VIEW_INDEX), E_USER_DEPRECATED);

        return $this->index($type, $_format, $request);
    }

    public function index(string $type, string $_format, Request $request): Response
    {
        $contentType = $this->contentTypeService->giveByName($type);
        $table = $this->initTable($contentType);
        $dataTableRequest = DataTableRequest::fromRequest($request);

        if ('json' === $_format) {
            $table->resetIterator($dataTableRequest);

            return $this->render('@EMSCore/datatable/ajax.html.twig', [
                'dataTableRequest' => $dataTableRequest,
                'table' => $table,
            ], new JsonResponse());
        }

        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form instanceof Form && ($action = $form->getClickedButton()) instanceof SubmitButton) {
                switch ($action->getName()) {
                    case EntityTable::DELETE_ACTION:
                        $this->viewManager->deleteByIds($table->getSelected());
                        break;
                    case TableType::REORDER_ACTION:
                        $newOrder = TableType::getReorderedKeys($form->getName(), $request);
                        $this->viewManager->reorderByIds($newOrder);
                        break;
                    default:
                        $this->logger->error('log.controller.view.unknown_action');
                }
            } else {
                $this->logger->error('log.controller.view.unknown_action');
            }

            return $this->redirectToRoute(Routes::VIEW_INDEX, [
                'type' => $contentType->getName(),
            ]);
        }

        return $this->render('@EMSCore/view/index.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @deprecated
     */
    public function addDeprecated(string $type, Request $request): Response
    {
        @\trigger_error(\sprintf('Route view.add is deprecated, use %s instead', Routes::VIEW_ADD), E_USER_DEPRECATED);

        return $this->add($type, $request);
    }

    public function add(string $type, Request $request): Response
    {
        $contentType = $this->contentTypeService->giveByName($type);
        $view = new View();
        $view->setContentType($contentType);

        $form = $this->createForm(ViewType::class, $view, [
            'create' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->viewManager->update($view);

            $this->logger->notice('log.view.created', [
                'view_name' => $view->getName(),
            ]);

            return $this->redirectToRoute(Routes::VIEW_EDIT, [
                'view' => $view->getId(),
            ]);
        }

        return $this->render('@EMSCore/view/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @deprecated
     */
    public function editDeprecated(View $view, string $_format, Request $request): Response
    {
        @\trigger_error(\sprintf('Route view.edit is deprecated, use %s instead', Routes::VIEW_EDIT), E_USER_DEPRECATED);

        return $this->edit($view, $_format, $request);
    }

    public function edit(View $view, string $_format, Request $request): Response
    {
        $form = $this->createForm(ViewType::class, $view, [
            'create' => false,
            'ajax-save-url' => $this->generateUrl(Routes::VIEW_EDIT, ['view' => $view->getId(), '_format' => 'json']),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->viewManager->update($view);

            $this->logger->notice('log.view.updated', [
                'view_name' => $view->getName(),
            ]);

            if ('json' === $_format) {
                return $this->render('@EMSCore/ajax/notification.json.twig', [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute(Routes::VIEW_INDEX, [
                'type' => $view->getContentType()->getName(),
            ]);
        }

        return $this->render('@EMSCore/view/edit.html.twig', [
            'form' => $form->createView(),
            'contentType' => $view->getContentType(),
            'view' => $view,
        ]);
    }

    public function duplicate(View $view): Response
    {
        $newView = clone $view;
        $this->viewManager->update($newView);

        return $this->redirectToRoute(Routes::VIEW_EDIT, ['view' => $newView->getId()]);
    }

    /**
     * @deprecated
     */
    public function deleteDeprecated(View $view): Response
    {
        @\trigger_error(\sprintf('Route view.delete is deprecated, use %s instead', Routes::VIEW_DELETE), E_USER_DEPRECATED);

        return $this->delete($view);
    }

    public function delete(View $view): Response
    {
        $name = $view->getName();
        $contentType = $view->getContentType();

        $this->viewManager->delete($view);
        $this->logger->notice('log.view.deleted', [
            'view_name' => $name,
        ]);

        return $this->redirectToRoute(Routes::VIEW_INDEX, [
            'type' => $contentType->getName(),
        ]);
    }

    private function initTable(ContentType $contentType): EntityTable
    {
        $table = new EntityTable($this->viewManager, $this->generateUrl(Routes::VIEW_INDEX, [
            'type' => $contentType->getName(),
            '_format' => 'json',
        ]), $contentType);
        $table->addColumn('table.index.column.loop_count', 'orderKey');
        $table->addColumnDefinition(new TemplateBlockTableColumn('dashboard.index.column.public', 'public', '@EMSCore/view/columns.html.twig'));
        $table->addColumn('view.index.column.name', 'name');
        $table->addColumn('view.index.column.label', 'label')->setItemIconCallback(function (View $view) {
            return $view->getIcon() ?? '';
        });
        $table->addColumnDefinition(new TranslationTableColumn('dashboard.index.column.type', 'type', EMSCoreBundle::TRANS_FORM_DOMAIN));
        $table->addItemGetAction(Routes::VIEW_EDIT, 'view.actions.edit', 'pencil');
        $table->addItemPostAction(Routes::VIEW_DUPLICATE, 'view.actions.duplicate', 'pencil', 'view.actions.duplicate_confirm');
        $table->addItemPostAction(Routes::VIEW_DELETE, 'view.actions.delete', 'trash', 'view.actions.delete_confirm')->setButtonType('outline-danger');
        $table->addTableAction(TableAbstract::DELETE_ACTION, 'fa fa-trash', 'view.actions.delete_selected', 'view.actions.delete_selected_confirm')
            ->setCssClass('btn btn-outline-danger');
        $table->setDefaultOrder('orderKey');

        return $table;
    }
}

<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeViewDataTableType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ViewController extends AbstractController
{
    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly ViewManager $viewManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace
    ) {
    }

    /** @deprecated */
    public function indexDeprecated(string $type, Request $request): Response
    {
        @\trigger_error(\sprintf('Route view.index is deprecated, use %s instead', Routes::VIEW_INDEX), E_USER_DEPRECATED);

        return $this->index($type, $request);
    }

    public function index(string $type, Request $request): Response
    {
        $contentType = $this->contentTypeService->giveByName($type);
        $table = $this->dataTableFactory->create(ContentTypeViewDataTableType::class, [
            'content_type_name' => $contentType->getName(),
        ]);

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

        return $this->render("@$this->templateNamespace/view/index.html.twig", [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function define(View $view, string $definition): Response
    {
        $this->viewManager->define($view, ViewDefinition::from($definition));

        return $this->redirectToRoute(Routes::VIEW_INDEX, ['type' => $view->getContentType()->getName()]);
    }

    public function undefine(View $view): Response
    {
        $this->viewManager->undefine($view);

        return $this->redirectToRoute(Routes::VIEW_INDEX, ['type' => $view->getContentType()->getName()]);
    }

    /** @deprecated */
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
                'view_label' => $view->getLabel(),
            ]);

            return $this->redirectToRoute(Routes::VIEW_EDIT, [
                'view' => $view->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/view/add.html.twig", [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /** @deprecated */
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
                'view_label' => $view->getLabel(),
            ]);

            if ('json' === $_format) {
                return $this->render("@$this->templateNamespace/ajax/notification.json.twig", [
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute(Routes::VIEW_INDEX, [
                'type' => $view->getContentType()->getName(),
            ]);
        }

        return $this->render("@$this->templateNamespace/view/edit.html.twig", [
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

    /** @deprecated */
    public function deleteDeprecated(View $view): Response
    {
        @\trigger_error(\sprintf('Route view.delete is deprecated, use %s instead', Routes::VIEW_DELETE), E_USER_DEPRECATED);

        return $this->delete($view);
    }

    public function delete(View $view): Response
    {
        $name = $view->getName();
        $label = $view->getLabel();
        $contentType = $view->getContentType();

        $this->viewManager->delete($view);
        $this->logger->notice('log.view.deleted', [
            'view_name' => $name,
            'view_label' => $label,
        ]);

        return $this->redirectToRoute(Routes::VIEW_INDEX, [
            'type' => $contentType->getName(),
        ]);
    }
}

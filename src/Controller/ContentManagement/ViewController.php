<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeViewDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Routes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class ViewController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly ViewManager $viewManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(ContentType $contentType, Request $request): Response
    {
        $table = $this->dataTableFactory->create(ContentTypeViewDataTableType::class, [
            'content_type_name' => $contentType->getName(),
        ]);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'content_type_view'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->viewManager->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->viewManager->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX, [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-filter',
            'title' => t(
                message: 'type.title_overview',
                parameters: ['type' => 'content_type_view', 'contentType' => $contentType->getSingularName()],
                domain: 'emsco-core'
            ),
            'breadcrumb' => Navigation::admin()->contentType($contentType)->contentTypeViews($contentType),
        ]);
    }

    public function define(View $view, string $definition): Response
    {
        $this->viewManager->define($view, ViewDefinition::from($definition));

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX, [
            'contentType' => $view->getContentType()->getId(),
        ]);
    }

    public function undefine(View $view): Response
    {
        $this->viewManager->undefine($view);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX, [
            'contentType' => $view->getContentType()->getId(),
        ]);
    }

    public function add(ContentType $contentType, Request $request): Response
    {
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

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_EDIT, [
                'view' => $view->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/view/add.html.twig", [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function edit(View $view, string $_format, Request $request): Response
    {
        $form = $this->createForm(ViewType::class, $view, [
            'create' => false,
            'ajax-save-url' => $this->generateUrl(Routes::ADMIN_CONTENT_TYPE_VIEW_EDIT, ['view' => $view->getId(), '_format' => 'json']),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->viewManager->update($view);

            $this->logger->notice('log.view.updated', [
                'view_name' => $view->getName(),
                'view_label' => $view->getLabel(),
            ]);

            if ('json' === $_format) {
                return $this->flashMessageLogger->buildJsonResponse([
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX, [
                'contentType' => $view->getContentType()->getId(),
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
        $newView->setDefinition(null);
        $this->viewManager->update($newView);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_EDIT, ['view' => $newView->getId()]);
    }

    public function delete(View $view): Response
    {
        $name = $view->getName();
        $label = $view->getLabel();

        $this->viewManager->delete($view);
        $this->logger->notice('log.view.deleted', [
            'view_name' => $name,
            'view_label' => $label,
        ]);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_VIEW_INDEX, [
            'contentType' => $view->getContentType()->getId(),
        ]);
    }
}

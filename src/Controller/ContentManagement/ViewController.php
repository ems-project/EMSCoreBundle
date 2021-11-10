<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Core\View\ViewManager;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function index(string $type): Response
    {
        return $this->render('@EMSCore/view/index.html.twig', [
            'contentType' => $this->contentTypeService->giveByName($type),
        ]);
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
                'id' => $view->getId(),
            ]);
        }

        return $this->render('@EMSCore/view/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function edit(View $view, string $_format, Request $request): Response
    {
        $form = $this->createForm(ViewType::class, $view, [
            'create' => false,
            'ajax-save-url' => $this->generateUrl(Routes::VIEW_EDIT, ['id' => $view->getId(), '_format' => 'json']),
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

        return $this->redirectToRoute(Routes::VIEW_EDIT, ['id' => $newView->getId()]);
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
}

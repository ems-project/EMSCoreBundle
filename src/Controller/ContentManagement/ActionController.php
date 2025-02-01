<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\DataTable\Type\ContentType\ContentTypeActionDataTableType;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\ActionType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ActionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

final class ActionController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly ActionService $actionService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LocalizedLoggerInterface $logger,
        private readonly TemplateRepository $templateRepository,
        private readonly FlashMessageLogger $flashMessageLogger,
        private readonly string $templateNamespace,
    ) {
    }

    public function index(Request $request, ContentType $contentType): Response
    {
        $table = $this->dataTableFactory->create(ContentTypeActionDataTableType::class, [
            'content_type_name' => $contentType->getName(),
        ]);

        $form = $this->createForm(TableType::class, $table, [
            'reorder_label' => t('type.reorder', ['type' => 'content_type_action'], 'emsco-core'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->actionService->deleteByIds(...$table->getSelected()),
                TableType::REORDER_ACTION => $this->actionService->reorderByIds(
                    ...TableType::getReorderedKeys($form->getName(), $request)
                ),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX, [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/crud/overview.html.twig", [
            'form' => $form->createView(),
            'icon' => 'fa fa-gear',
            'title' => t(
                message: 'type.title_overview',
                parameters: ['type' => 'content_type_action', 'contentType' => $contentType->getSingularName()],
                domain: 'emsco-core'
            ),
            'breadcrumb' => Navigation::admin()->contentType($contentType)->contentTypeActions($contentType),
        ]);
    }

    public function add(ContentType $contentType, Request $request): Response
    {
        $action = new Template();
        $action->setContentType($contentType);

        $form = $this->createForm(ActionType::class, $action);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $action->setOrderKey($this->actionService->count('', $contentType) + 1);
            $action->setName(new Encoder()->slug(text: $action->getName(), separator: '_')->toString());
            $this->templateRepository->save($action);
            $this->logger->notice('log.action.added', [
                'action_name' => $action->getName(),
            ]);

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX, [
                'contentType' => $contentType->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/action/add.html.twig", [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    public function edit(Template $action, Request $request, string $_format): Response
    {
        $id = $action->getId();

        $form = $this->createForm(ActionType::class, $action, [
            'ajax-save-url' => $this->generateUrl(Routes::ADMIN_CONTENT_TYPE_ACTION_EDIT, ['contentType' => $action->getContentType(), 'action' => $id, '_format' => 'json']),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->templateRepository->save($action);
            $this->logger->notice('log.action.updated', [
                'action_name' => $action->getName(),
            ]);

            if ('json' === $_format) {
                return $this->flashMessageLogger->buildJsonResponse([
                    'success' => true,
                ]);
            }

            return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX, [
                'contentType' => $action->giveContentType()->getId(),
            ]);
        }

        if ('json' === $_format) {
            foreach ($form->getErrors() as $error) {
                if ($error instanceof FormError) {
                    $this->logger->error('log.error', [EmsFields::LOG_ERROR_MESSAGE_FIELD => $error->getMessage()]);
                }
            }

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => $form->isValid(),
            ]);
        }

        return $this->render("@$this->templateNamespace/action/edit.html.twig", [
            'form' => $form->createView(),
            'action' => $action,
            'contentType' => $action->giveContentType(),
        ]);
    }

    public function delete(Template $action): RedirectResponse
    {
        $this->actionService->delete($action);
        $this->logger->notice('log.action.deleted', [
            'action_name' => $action->getName(),
        ]);

        return $this->redirectToRoute(Routes::ADMIN_CONTENT_TYPE_ACTION_INDEX, [
            'contentType' => $action->giveContentType()->getId(),
        ]);
    }
}

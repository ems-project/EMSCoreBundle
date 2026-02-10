<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Webhook;

use EMS\ClientHelperBundle\Helper\Webhook\Webhook;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Controller\CoreControllerTrait;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Core\Webhook\WebhookSubscriptionManager;
use EMS\CoreBundle\DataTable\Type\WebhookSubscriptionDataTableType;
use EMS\CoreBundle\Entity\WebhookSubscription;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\WebhookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

use function Symfony\Component\Translation\t;

class WebhookController extends AbstractController
{
    use CoreControllerTrait;

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly WebhookSubscriptionManager $webhookSubscriptionManager,
        private readonly DataTableFactory $dataTableFactory,
        private readonly WebhookService $webhookService,
    ) {
    }

    public function index(Request $request): Page|RedirectResponse
    {
        $table = $this->dataTableFactory->create(WebhookSubscriptionDataTableType::class);
        $form = $this->createForm(TableType::class, $table);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            match ($this->getClickedButtonName($form)) {
                TableAbstract::DELETE_ACTION => $this->webhookSubscriptionManager->deleteByIds($table->getSelected()),
                default => $this->logger->messageError(t('log.error.invalid_table_action', [], 'emsco-core')),
            };

            return $this->redirectToRoute(Routes::WEBHOOK_SUBSCRIPTION_INDEX);
        }

        return new Page([
            'datatable' => ['form' => $form->createView()],
            'title' => t('type.title_overview', ['type' => 'webhook'], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'webhook'], 'emsco-core'),
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    public function delete(WebhookSubscription $webhookSubscription): Response
    {
        $this->webhookSubscriptionManager->delete($webhookSubscription);

        return $this->redirectToRoute(Routes::WEBHOOK_SUBSCRIPTION_INDEX);
    }

    public function test(Request $request, WebhookSubscription $webhookSubscription): Response
    {
        if (Request::METHOD_POST !== $request->getMethod()) {
            throw new MethodNotAllowedHttpException([Request::METHOD_POST]);
        }
        if (!$webhookSubscription->isEnabled()) {
            $this->logger->messageNotice(t('webhook.enable.re-enabled', [
                'id' => $webhookSubscription->getId(),
            ], 'emsco-core'));
            $this->webhookService->enable($webhookSubscription);
        }
        $this->webhookService->dispatchTo($webhookSubscription, Webhook::WEBHOOK_TEST, [
            'message' => 'This is a test event',
        ]);
        $this->logger->messageNotice(t('webhook.test.dispatched', [
            'id' => $webhookSubscription->getId(),
        ], 'emsco-core'));

        return $this->redirectToRoute(Routes::WEBHOOK_SUBSCRIPTION_INDEX);
    }

    public function toggleEnable(Request $request, WebhookSubscription $webhookSubscription): Response
    {
        if (Request::METHOD_POST !== $request->getMethod()) {
            throw new MethodNotAllowedHttpException([Request::METHOD_POST]);
        }
        if (!$webhookSubscription->isEnabled()) {
            $this->logger->messageNotice(t('webhook.enable.re-enabled', [
                'id' => $webhookSubscription->getId(),
            ], 'emsco-core'));
            $this->webhookService->enable($webhookSubscription);
        } else {
            $this->logger->messageNotice(t('webhook.enable.disabled', [
                'id' => $webhookSubscription->getId(),
            ], 'emsco-core'));
            $this->webhookService->enable($webhookSubscription, false);
        }

        return $this->redirectToRoute(Routes::WEBHOOK_SUBSCRIPTION_INDEX);
    }

    private function breadcrumb(): Navigation
    {
        return Navigation::admin()->add(
            label: t('key.webhooks', [], 'emsco-core'),
            icon: 'fa fa-chain',
        )->add(
            label: t('key.webhook_subscriptions', [], 'emsco-core'),
            icon: 'fa fa-solid fa-registered',
            route: Routes::WEBHOOK_SUBSCRIPTION_INDEX,
        );
    }
}

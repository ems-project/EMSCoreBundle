<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Webhook\WebhookSubscriptionManager;
use EMS\CoreBundle\Form\Data\BoolTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\StringArrayTableColumn;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class WebhookSubscriptionDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(WebhookSubscriptionManager $entityService)
    {
        parent::__construct($entityService);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->addColumnDefinition(new BoolTableColumn(
            titleKey: t('field.enabled', [], 'emsco-core'),
            attribute: 'enabled'
        ));
        $table->addColumn(t('field.endpoint_url', [], 'emsco-core'), 'endpointUrl');
        $table->addColumnDefinition(new StringArrayTableColumn(
            titleKey: t('field.events', [], 'emsco-core'),
            attribute: 'events'
        ));
        $table->addColumn(t('field.error', [], 'emsco-core'), 'errorMessage');
        $this
            ->addColumnsCreatedModifiedDate($table)
            ->addTableActionDelete($table, 'webhook_subscription');
        $table->addItemPostAction(Routes::WEBHOOK_SUBSCRIPTION_TOGGLE_ENABLE, t('webhook.enable.action', [], 'emsco-core'), 'check-square-o', t('action.confirmation', [], 'emsco-core'));
        $table->addItemPostAction(Routes::WEBHOOK_SUBSCRIPTION_TEST, t('webhook.test.action', [], 'emsco-core'), 'check', t('action.confirmation', [], 'emsco-core'));
        $this->addItemDelete($table, 'webhook_subscription', Routes::WEBHOOK_SUBSCRIPTION_DELETE);
    }
}

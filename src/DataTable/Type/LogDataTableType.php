<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Log\LogEntityTableContext;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;

use function Symfony\Component\Translation\t;

class LogDataTableType extends AbstractEntityTableType
{
    use DataTableTypeTrait;

    public function __construct(LogManager $logManager)
    {
        parent::__construct($logManager);
    }

    #[\Override]
    public function getContext(array $options): LogEntityTableContext
    {
        return new LogEntityTableContext();
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table->setDefaultOrder('created', 'desc')->setLabelAttribute('id');

        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_created', [], 'emsco-core'),
            attribute: 'created'
        ));
        $table->addColumn(
            titleKey: t('field.channel', [], 'emsco-core'),
            attribute: 'channel'
        );
        $table->addColumn(
            titleKey: t('field.severity', [], 'emsco-core'),
            attribute: 'levelName'
        );
        $table->addColumn(
            titleKey: t('field.message', [], 'emsco-core'),
            attribute: 'message'
        );
        $table->addColumnDefinition(new UserTableColumn(
            titleKey: t('field.user', [], 'emsco-core'),
            attribute: 'username'
        ));
        $table->addColumnDefinition(new UserTableColumn(
            titleKey: t('field.user_impersonator', [], 'emsco-core'),
            attribute: 'impersonator'
        ));

        $table->addItemGetAction(
            route: Routes::LOG_VIEW,
            labelKey: t('action.details', [], 'emsco-core'),
            icon: 'eye'
        );

        $this
            ->addItemDelete($table, 'log', Routes::LOG_DELETE)
            ->addTableActionDelete($table, 'log');
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }
}

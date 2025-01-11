<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\Log\LogEntityTableContext;
use EMS\CoreBundle\Core\Log\LogManager;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableColumn;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionAuditDataTableType extends AbstractEntityTableType
{
    public function __construct(
        LogManager $logManager,
        private readonly RevisionService $revisionService,
    ) {
        parent::__construct($logManager);
    }

    #[\Override]
    public function build(EntityTable $table): void
    {
        $table
            ->addColumnDefinition(new DatetimeTableColumn('log.index.column.created', 'created'))
            ->setCellClass('col-sm');
        $table
            ->addColumnDefinition(new TableColumn('log.index.column.level_name', 'levelName'))
            ->setCellClass('text-center col-xs');
        $table
            ->addColumnDefinition(new TableColumn('log.index.column.message', 'message'));
        $table
            ->addColumnDefinition(new UserTableColumn('log.index.column.username', 'username'))
            ->setCellClass('col-sm');
        $table->setDefaultOrder('created', 'desc');
    }

    #[\Override]
    public function getContext(array $options): LogEntityTableContext
    {
        $revision = $this->revisionService->getByRevisionId($options['revision_id']);

        $context = new LogEntityTableContext();
        $context->revision = $revision;
        $context->channels = ['audit'];

        return $context;
    }

    #[\Override]
    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN, Roles::ROLE_AUDITOR];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['revision_id']);
    }
}

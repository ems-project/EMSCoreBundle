<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Data\Condition\DateInFuture;
use EMS\CoreBundle\Form\Data\Condition\InMyCircles;
use EMS\CoreBundle\Form\Data\Condition\NotEmpty;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\RevisionDisplayTableColumn;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\Translation\t;

class RevisionDraftsDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    final public const DISCARD_SELECTED_DRAFT = 'DISCARD_SELECTED_DRAFT';

    public function __construct(
        private readonly RevisionRepository $revisionRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ContentTypeService $contentTypeService,
        private readonly UserService $userService,
    ) {
    }

    public function build(QueryTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();

        $table
            ->setLabelAttribute('label')
            ->setExtraFrontendOption(['order' => [2, 'desc']])
            ->setDefaultOrder('modified', 'desc');

        $table->addColumnDefinition(new RevisionDisplayTableColumn(t('field.label', [], 'emsco-core'), 'label'))->setOrderField('labelField');
        $table->addColumnDefinition(new DatetimeTableColumn(t('field.date_modified', [], 'emsco-core'), 'draftSaveDate'));
        $table->addColumnDefinition(new UserTableColumn(t('field.user_modified', [], 'emsco-core'), 'autoSaveBy'));

        $lockUntil = new DatetimeTableColumn(t('revision.field.locked', [], 'emsco-core'), 'lockUntil');
        $condition = new DateInFuture('lockUntil');
        $lockUntil->addCondition($condition);
        $table->addColumnDefinition($lockUntil);
        $lockBy = new UserTableColumn(t('revision.field.locked_by', [], 'emsco-core'), 'lockBy');
        $lockBy->addCondition($condition);
        $table->addColumnDefinition($lockBy);

        $inMyCircles = new InMyCircles($this->userService);

        $table->addDynamicItemGetAction(
            route: Routes::EDIT_REVISION,
            labelKey: t('revision.draft.edit', [], 'emsco-core'),
            icon: 'pencil',
            routeParameters: ['revisionId' => 'id']
        )->addCondition($inMyCircles)->setButtonType('primary');

        $table->addDynamicItemGetAction(
            route: Routes::VIEW_REVISIONS,
            labelKey: t('revision.draft.view', [], 'emsco-core'),
            icon: '',
            routeParameters: ['type' => 'contentType.name', 'ouuid' => 'ouuid',
            ])->addCondition(new NotEmpty('ouuid'));

        $table->addDynamicItemPostAction(
            route: Routes::DISCARD_DRAFT,
            labelKey: t('revision.draft.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => 'draft'], 'emsco-core'),
            routeParameters: ['revisionId' => 'id']
        )->addCondition($inMyCircles)->setButtonType('outline-danger');

        if (null !== $contentType && (null === $contentType->getCirclesField() || '' === $contentType->getCirclesField())) {
            $this->addTableActionDelete($table, 'draft', self::DISCARD_SELECTED_DRAFT);
        }
    }

    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }

    public function getQueryName(): string
    {
        return 'draft_in_progress';
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        $qb = $this->createQueryBuilder($context, $searchValue);
        $qb->setFirstResult($from)->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy(\sprintf('r.%s', $orderField), $orderDirection);
        }

        return $qb->getQuery()->execute();
    }

    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        if (!$context instanceof ContentType) {
            throw new \RuntimeException('Unexpected context');
        }

        return (int) $this->createQueryBuilder($context, $searchValue)
            ->select('count(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createQueryBuilder(ContentType $contentType, string $searchValue = ''): QueryBuilder
    {
        return $this->revisionRepository->makeQueryBuilder(
            contentTypeName: $contentType->getName(),
            isDraft: true,
            isAdmin: $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            circles: $this->userService->getCurrentUser()->getCircles(),
            searchValue: $searchValue
        );
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\Revision;

use Doctrine\ORM\QueryBuilder;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function Symfony\Component\Translation\t;

class RevisionTrashDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public const ACTION_EMPTY_TRASH = 'empty-trash';
    public const ACTION_PUT_BACK = 'put-back';

    public function __construct(
        private readonly RevisionRepository $revisionRepository,
        private readonly UserService $userService,
        private readonly ContentTypeService $contentTypeService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function build(QueryTable $table): void
    {
        /** @var ContentType $contentType */
        $contentType = $table->getContext();

        $table
            ->setIdField('ouuid')
            ->setLabelAttribute('label')
            ->setDefaultOrder('modified', 'desc');

        $table->addColumn(t('field.label', [], 'emsco-core'), 'label');
        if ($this->userService->isSuper()) {
            $table->addColumn(t('revision.field.ouuid', [], 'emsco-core'), 'ouuid');
        }
        $table->addColumnDefinition(new UserTableColumn(t('field.user_deleted', [], 'emsco-core'), 'deletedBy'));
        $table->addColumnDefinition(new DatetimeTableColumn(t('field.date_modified', [], 'emsco-core'), 'modified'));

        if ($this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::CREATE))) {
            $table->addDynamicItemPostAction(
                route: Routes::DATA_TRASH_PUT_BACK,
                labelKey: t('revision.trash.put_back', [], 'emsco-core'),
                icon: 'recycle',
                messageKey: t('revision.trash.put_back_confirm', [], 'emsco-core'),
                routeParameters: [
                    'contentType' => $contentType->getId(),
                    'ouuid' => 'ouuid',
                ]
            );
            $table->addTableAction(
                name: self::ACTION_PUT_BACK,
                icon: 'fa fa-recycle',
                labelKey: t('revision.trash.put_back_selected', [], 'emsco-core')
            );
        }

        $table->addDynamicItemPostAction(
            route: Routes::DATA_TRASH_EMPTY,
            labelKey: t('action.delete', [], 'emsco-core'),
            icon: 'trash',
            messageKey: t('type.delete_confirm', ['type' => 'trash'], 'emsco-core'),
            routeParameters: [
                'contentType' => $contentType->getId(),
                'ouuid' => 'ouuid',
            ]
        )->setButtonType('outline-danger');

        $this->addTableActionDelete($table, 'trash', self::ACTION_EMPTY_TRASH);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired(['content_type_name']);
    }

    /**
     * @param array{'content_type_name': string} $options
     */
    public function getContext(array $options): ContentType
    {
        return $this->contentTypeService->giveByName($options['content_type_name']);
    }

    public function getQueryName(): string
    {
        return 'revision_trash';
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
            isDeleted: true,
            isAdmin: $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            circles: $this->userService->getCurrentUser()->getCircles(),
            searchValue: $searchValue
        );
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\ContentType;

use EMS\CoreBundle\Core\ContentType\ContentTypeUnreferenced;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TemplateBlockTableColumn;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;

use function Symfony\Component\Translation\t;

class ContentTypeUnreferencedDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly string $templateNamespace
    ) {
    }

    public function build(QueryTable $table): void
    {
        $table->setDefaultOrder('name')->setLabelAttribute('name');

        $table->addColumn(t('field.name', [], 'emsco-core'), 'name');

        $table->addColumnDefinition(new TemplateBlockTableColumn(
            label: t('field.environment_external', [], 'emsco-core'),
            blockName: 'contentTypeEnvironment',
            template: "@$this->templateNamespace/datatable/template_block_columns.html.twig",
            orderField: 'environmentLabel'
        ));

        $table->addColumn(t('field.count', [], 'emsco-core'), 'count');

        $table->addDynamicItemPostAction(
            route: Routes::ADMIN_CONTENT_TYPE_ADD_REFERENCED,
            labelKey: t('action.add_referenced', [], 'emsco-core'),
            icon: 'plus',
            messageKey: t('type.confirm', ['type' => 'content_type_referenced_add'], 'emsco-core'),
            routeParameters: ['environment' => 'environmentId', 'name' => 'name']
        )->setButtonType('primary');
    }

    public function getRoles(): array
    {
        return [Roles::ROLE_ADMIN];
    }

    public function getQueryName(): string
    {
        return 'contentTypeUnreferenced';
    }

    public function isSortable(): bool
    {
        return false;
    }

    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $unreferencedContentTypes = $this->getUnreferencedContentTypes();

        if ($orderField) {
            $this->sortUnreferencedContentTypes($unreferencedContentTypes, $orderField, $orderDirection);
        }

        return $unreferencedContentTypes;
    }

    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return \count($this->getUnreferencedContentTypes());
    }

    /**
     * @return ContentTypeUnreferenced[]
     */
    private function getUnreferencedContentTypes(): array
    {
        static $unreferencedContentTypes = null;

        if (null === $unreferencedContentTypes) {
            $unreferencedContentTypes = $this->contentTypeService->getUnreferencedContentTypes();
        }

        return $unreferencedContentTypes;
    }

    /**
     * @param ContentTypeUnreferenced[] $contentTypes
     */
    private function sortUnreferencedContentTypes(array &$contentTypes, string $orderField, string $orderDirection): void
    {
        \usort($contentTypes, static function (
            ContentTypeUnreferenced $a,
            ContentTypeUnreferenced $b
        ) use ($orderField, $orderDirection): int {
            if ('count' === $orderField) {
                $result = $a->{$orderField} <=> $b->{$orderField};
            } else {
                $result = \strcmp($a->{$orderField}, $b->{$orderField});
            }

            return 'desc' === $orderDirection ? $result * -1 : $result;
        });
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\DataTable\Type\UploadedAsset;

use Doctrine\ORM\QueryBuilder;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Helper\Text\Encoder;
use EMS\CoreBundle\Core\DataTable\Type\AbstractTableType;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\DataTable\Type\DataTableTypeTrait;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Data\BytesTableColumn;
use EMS\CoreBundle\Form\Data\DatetimeTableColumn;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Form\Data\TranslationTableColumn;
use EMS\CoreBundle\Form\Data\UserTableColumn;
use EMS\CoreBundle\Repository\UploadedAssetRepository;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\Translation\t;

class UploadedAssetDataTableType extends AbstractTableType implements QueryServiceTypeInterface
{
    use DataTableTypeTrait;

    public const HIDE_ACTION = 'hide';

    public const LOCATION_WYSIWYG_BROWSER = 'wysiwyg_browser';
    public const LOCATION_FILE_MODAL = 'file_modal';
    public const LOCATION_PUBLISHER_OVERVIEW = 'publisher_overview';

    public function __construct(
        private readonly UploadedAssetRepository $uploadedAssetRepository,
        private readonly RouterInterface $router,
    ) {
    }

    #[\Override]
    public function build(QueryTable $table): void
    {
        /** @var array{'location': string} $context */
        $context = $table->getContext();
        $location = $context['location'];
        $router = $this->router;

        $table->setDefaultOrder('name')->setLabelAttribute('name');

        $columnName = $table->addColumn(t('field.name', [], 'emsco-core'), 'name');
        $columnName->setRoute('ems_file_download', fn (array $data) => [
            'sha1' => $data['id'],
            'type' => $data['type'],
            'name' => $data['name'],
        ]);

        if (self::LOCATION_WYSIWYG_BROWSER === $location || self::LOCATION_FILE_MODAL === $location) {
            $columnName->addHtmlAttribute('data-url', fn (array $data) => \vsprintf('%s%s?name=%s&type=%s', [
                EMSLink::EMSLINK_ASSET_PREFIX,
                $data['id'],
                $data['name'],
                $data['type'],
            ]));
            $columnName->addHtmlAttribute('data-json', fn (array $data) => Json::encode([
                EmsFields::CONTENT_FILE_NAME_FIELD => $data['name'],
                EmsFields::CONTENT_FILE_SIZE_FIELD => $data['size'] ?? 0,
                EmsFields::CONTENT_MIME_TYPE_FIELD => $data['type'],
                EmsFields::CONTENT_FILE_HASH_FIELD => $data['id'],
                EmsFields::CONTENT_HASH_ALGO_FIELD => $data['hash_algo'] ?? EmsFields::CONTENT_FILE_HASH_FIELD,
                'preview_url' => $router->generate('ems_asset_processor', [
                    'hash' => $data['id'],
                    'processor' => 'preview',
                    'type' => $data['type'],
                    'name' => $data['name'],
                ]),
                'view_url' => $router->generate('ems.file.view', [
                    'sha1' => $data['id'],
                    'type' => $data['type'],
                    'name' => $data['name'],
                ]),
            ]));
        }

        if (self::LOCATION_FILE_MODAL === $location) {
            $columnName->setItemIconCallback(fn (array $data) => Encoder::getFontAwesomeFromMimeType($data['type'], EMSCoreBundle::FONTAWESOME_VERSION));
        } else {
            $table->addColumnDefinition(new TranslationTableColumn(
                titleKey: t('field.file.type', [], 'emsco-core'),
                attribute: 'type',
                domain: 'emsco-mimetypes'
            ))->setItemIconCallback(fn (array $data) => Encoder::getFontAwesomeFromMimeType($data['type'], EMSCoreBundle::FONTAWESOME_VERSION));
        }

        $table->addColumnDefinition(new UserTableColumn(
            titleKey: t('field.user_uploaded', [], 'emsco-core'),
            attribute: 'user'
        ));

        $table->addColumnDefinition(new DatetimeTableColumn(
            titleKey: t('field.date_upload', [], 'emsco-core'),
            attribute: 'created'
        ));

        if (self::LOCATION_PUBLISHER_OVERVIEW === $location) {
            $table->addColumnDefinition(new DatetimeTableColumn(
                titleKey: t('field.date_modified', [], 'emsco-core'),
                attribute: 'modified'
            ));
        }

        $table->addColumnDefinition(new BytesTableColumn(
            titleKey: t('field.file.size', [], 'emsco-core'),
            attribute: 'size'
        ))->setCellClass('text-right');

        if (self::LOCATION_PUBLISHER_OVERVIEW === $location) {
            $table->addDynamicItemPostAction(
                route: Routes::UPLOAD_ASSET_PUBLISHER_HIDE,
                labelKey: t('action.delete', [], 'emsco-core'),
                icon: 'trash',
                messageKey: t('type.delete_confirm', ['type' => 'uploaded_file'], 'emsco-core'),
                routeParameters: ['hash' => 'id']
            )->setButtonType('outline-danger');

            $table->addTableAction(
                name: TableAbstract::DOWNLOAD_ACTION,
                icon: 'fa fa-download',
                labelKey: t('action.download_selected', [], 'emsco-core')
            )->setCssClass('btn btn-sm btn-default');

            $this->addTableActionDelete($table, 'uploaded_file', self::HIDE_ACTION);
        }
    }

    /**
     * @param array{'location': string} $options
     *
     * @return array{ 'location': string }
     */
    #[\Override]
    public function getContext(array $options): mixed
    {
        return ['location' => $options['location']];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver
            ->setRequired('location')
            ->setAllowedValues('location', [
                self::LOCATION_WYSIWYG_BROWSER,
                self::LOCATION_PUBLISHER_OVERVIEW,
                self::LOCATION_FILE_MODAL,
            ]);
    }

    #[\Override]
    public function getQueryName(): string
    {
        return 'uploaded_asset';
    }

    #[\Override]
    public function isSortable(): bool
    {
        return false;
    }

    #[\Override]
    public function query(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        $qb = $this->createQueryBuilder($searchValue);
        $qb
            ->select('ua.sha1 as id')
            ->addSelect('max(ua.name) as name')
            ->addSelect('max(ua.size) as size')
            ->addSelect('max(ua.type) as type')
            ->addSelect('max(ua.user) as user')
            ->addSelect('max(ua.hashAlgo) as hash_algo')
            ->addSelect('min(ua.created) as created')
            ->addSelect('max(ua.modified) as modified')
            ->groupBy('ua.sha1')
            ->setFirstResult($from)
            ->setMaxResults($size);

        if (null !== $orderField) {
            $qb->orderBy($orderField, $orderDirection);
        }

        return $qb->getQuery()->getArrayResult();
    }

    #[\Override]
    public function countQuery(string $searchValue = '', mixed $context = null): int
    {
        return (int) $this->createQueryBuilder($searchValue)
            ->select('count(DISTINCT ua.sha1)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createQueryBuilder(string $searchValue = ''): QueryBuilder
    {
        return $this->uploadedAssetRepository->makeQueryBuilder(
            hidden: false,
            available: true,
            searchValue: $searchValue
        );
    }
}

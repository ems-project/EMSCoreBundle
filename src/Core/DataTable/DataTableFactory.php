<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\DataTable\Type\AbstractQueryTableType;
use EMS\CoreBundle\Core\DataTable\Type\DataTableFilterFormInterface;
use EMS\CoreBundle\Core\DataTable\Type\DataTableTypeCollection;
use EMS\CoreBundle\Core\DataTable\Type\DataTableTypeInterface;
use EMS\CoreBundle\Core\DataTable\Type\QueryServiceTypeInterface;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\QueryTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Hash;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

class DataTableFactory
{
    public function __construct(
        private readonly DataTableTypeCollection $typeCollection,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheItemPoolInterface $cache,
        private readonly Security $security,
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $requestStack,
        private readonly string $templateNamespace,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(string $class, array $options = []): TableAbstract
    {
        $type = $this->typeCollection->getByClass($class);

        return $this->build($type, $options);
    }

    public function createFromHash(string $hash, ?string $optionsCacheKey, DataTableFormat $format = DataTableFormat::TABLE): TableAbstract
    {
        $type = $this->typeCollection->getByHash($hash);
        $type->setFormat($format);

        $options = $optionsCacheKey ? $this->cache->getItem($optionsCacheKey)->get() : [];

        return $this->build($type, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function build(DataTableTypeInterface $type, array $options): TableAbstract
    {
        $options = $this->resolveOptions($type, $options);
        $optionsCacheKey = $this->getOptionsCacheKey($options);

        $this->checkRoles($type, $options['roles']);
        $context = $type->getContext($options);

        $filterForm = $this->buildFilterForm($type, $context);
        $context = $this->addFilterFormToContext($type, $context, $filterForm);

        $ajaxParams = $this->generateUrlParams($type, $optionsCacheKey, $filterForm);
        $ajaxUrl = $this->generateUrl('ajax_table', $ajaxParams);

        $table = match (true) {
            $type instanceof AbstractEntityTableType => $this->buildEntityTable($type, $ajaxUrl, $context),
            $type instanceof AbstractQueryTableType => $this->buildQueryTable($type, $ajaxUrl, $context),
            $type instanceof QueryServiceTypeInterface => $this->buildQueryServiceType($type, $ajaxUrl, $context),
            default => throw new \RuntimeException('Unknown dataTableType'),
        };

        $table->setFilterForm($filterForm);

        foreach ($type->getExportFormats() as $format) {
            $exportParams = [...$ajaxParams, ...['format' => $format->value]];
            $table->addExportUrl($format->value, $this->generateUrl('ajax_export', $exportParams));
        }

        return $table;
    }

    private function buildEntityTable(AbstractEntityTableType $type, string $ajaxUrl, mixed $context): EntityTable
    {
        $table = new EntityTable(
            $this->templateNamespace,
            $type->getEntityService(),
            $ajaxUrl,
            $context,
            $type->getLoadMaxRows()
        );

        $type->build($table);

        return $table;
    }

    private function buildQueryTable(AbstractQueryTableType $type, string $ajaxUrl, mixed $context): QueryTable
    {
        $table = new QueryTable(
            $this->templateNamespace,
            $type->getQueryService(),
            $type->getQueryName(),
            $ajaxUrl,
            $context,
            $type->getLoadMaxRows()
        );

        $type->build($table);

        return $table;
    }

    private function buildQueryServiceType(QueryServiceTypeInterface $type, string $ajaxUrl, mixed $context): QueryTable
    {
        $table = new QueryTable(
            $this->templateNamespace,
            $type,
            $type->getQueryName(),
            $ajaxUrl,
            $context,
            $type->getLoadMaxRows()
        );

        $type->build($table);

        return $table;
    }

    private function buildFilterForm(DataTableTypeInterface $type, mixed $context): ?FormInterface
    {
        if (!$type instanceof DataTableFilterFormInterface) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $filterForm = $type->filterFormBuild($this->formFactory, $context);
        $filterForm->handleRequest($request);

        return $filterForm;
    }

    private function addFilterFormToContext(DataTableTypeInterface $type, mixed $context, ?FormInterface $filterForm = null): mixed
    {
        if (!$type instanceof DataTableFilterFormInterface || null === $filterForm) {
            return $context;
        }

        return $type->filterFormAddToContext($filterForm, $context);
    }

    /**
     * @param string[] $optionsRoles
     */
    private function checkRoles(DataTableTypeInterface $type, array $optionsRoles): void
    {
        $roles = [...$type->getRoles(), ...$optionsRoles];

        $grantedRoles = \array_filter($roles, fn (string $role) => $this->security->isGranted($role));

        if (0 === \count($grantedRoles)) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generateUrl(string $name, array $params): string
    {
        return match ($name) {
            'ajax_table' => $this->urlGenerator->generate(Routes::DATA_TABLE_AJAX_TABLE, $params),
            'ajax_export' => $this->urlGenerator->generate(Routes::DATA_TABLE_AJAX_TABLE_EXPORT, $params),
            default => throw new \RuntimeException(\sprintf('Could not generate url for "%s"', $name)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function generateUrlParams(DataTableTypeInterface $type, ?string $optionsCacheKey, ?FormInterface $filterForm): array
    {
        $params = [
            'hash' => $type->getHash(),
            'optionsCacheKey' => $optionsCacheKey,
        ];

        if ($filterForm) {
            $request = $this->requestStack->getCurrentRequest();
            $name = $filterForm->getConfig()->getName();
            $params[$name] = $request?->query->all($name);
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getOptionsCacheKey(array $options): ?string
    {
        if (0 === \count($options)) {
            return null;
        }

        $key = Hash::array($options);
        $item = $this->cache->getItem($key)->set($options);
        $this->cache->save($item);

        return $key;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(DataTableTypeInterface $type, array $options): array
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver
            ->setDefaults([
                'roles' => [],
            ])
            ->setAllowedTypes('roles', 'string[]');

        $type->configureOptions($optionsResolver);

        return $optionsResolver->resolve($options);
    }
}

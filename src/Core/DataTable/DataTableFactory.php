<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

use EMS\CoreBundle\Core\DataTable\Type\AbstractEntityTableType;
use EMS\CoreBundle\Core\DataTable\Type\DataTableTypeCollection;
use EMS\CoreBundle\Core\DataTable\Type\DataTableTypeInterface;
use EMS\CoreBundle\Form\Data\EntityTable;
use EMS\CoreBundle\Form\Data\TableAbstract;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Hash;
use Psr\Cache\CacheItemPoolInterface;
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

    public function createFromHash(string $hash, ?string $optionsCacheKey): TableAbstract
    {
        $type = $this->typeCollection->getByHash($hash);
        $options = $optionsCacheKey ? $this->cache->getItem($optionsCacheKey)->get() : [];

        return $this->build($type, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function build(DataTableTypeInterface $type, array $options): TableAbstract
    {
        $this->checkRoles($type);

        $options = $this->resolveOptions($type, $options);
        $optionsCacheKey = $this->getOptionsCacheKey($options);
        $ajaxUrl = $this->generateAjaxUrl($type, $optionsCacheKey);

        return match (true) {
            $type instanceof AbstractEntityTableType => $this->buildEntityTable($type, $ajaxUrl, $options),
            default => throw new \RuntimeException('Unknown dataTableType')
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildEntityTable(AbstractEntityTableType $type, string $ajaxUrl, array $options = []): EntityTable
    {
        $table = new EntityTable(
            $type->getEntityService(),
            $ajaxUrl,
            $type->getContext($options),
            $type->getLoadMaxRows()
        );

        $type->build($table);

        return $table;
    }

    private function checkRoles(DataTableTypeInterface $type): void
    {
        $roles = $type->getRoles();
        $grantedRoles = \array_filter($roles, fn (string $role) => $this->security->isGranted($role));

        if (0 === \count($grantedRoles)) {
            throw new AccessDeniedException();
        }
    }

    private function generateAjaxUrl(DataTableTypeInterface $type, ?string $optionsCacheKey = null): string
    {
        return $this->urlGenerator->generate(Routes::DATA_TABLE_AJAX_TABLE, [
            'hash' => $type->getHash(),
            'optionsCacheKey' => $optionsCacheKey,
        ]);
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
        $type->configureOptions($optionsResolver);

        return $optionsResolver->resolve($options);
    }
}

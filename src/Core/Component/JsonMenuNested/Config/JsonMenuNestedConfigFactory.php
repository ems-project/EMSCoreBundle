<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\JsonMenuNested\Config;

use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Core\Config\AbstractConfigFactory;
use EMS\CoreBundle\Service\Revision\RevisionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class JsonMenuNestedConfigFactory extends AbstractConfigFactory
{
    public function __construct(private readonly RevisionService $revisionService)
    {
    }

    /**
     * @param array{
     *     id: string,
     *     ems_link: EMSLink,
     *     field_path: string,
     *     context: array<string, mixed>,
     *     context_block: ?string,
     *     template: ?string,
     *     locale: ?string,
     *     active_item_id: ?string,
     *     columns: JsonMenuNestedColumn[]
     * } $options
     */
    #[\Override]
    protected function create(string $hash, array $options): JsonMenuNestedConfig
    {
        if (null === $revision = $this->revisionService->getByEmsLink($options['ems_link'])) {
            throw new NotFoundHttpException('Revision not found');
        }

        $structure = (new PropertyAccessor())->getValue($revision->getData(), $options['field_path']);
        if (null === $fieldType = $revision->giveContentType()->getFieldType()->findChildByPath($options['field_path'])) {
            throw new NotFoundHttpException('Field type not found');
        }

        $jsonMenuNested = JsonMenuNested::fromStructure($structure ?? '{}');
        $jsonMenuNested->setType($fieldType->getName());
        $jsonMenuNestedNodes = new JsonMenuNestedNodes($fieldType);

        $config = new JsonMenuNestedConfig(
            $hash,
            (string) $options['id'],
            $revision,
            $jsonMenuNested,
            $jsonMenuNestedNodes
        );

        $config->context = $options['context'];
        $config->contextBlock = $options['context_block'];
        $config->template = $options['template'];
        $config->columns = $options['columns'];
        $config->locale = $options['locale'];
        $config->activeItemId = $options['active_item_id'];

        return $config;
    }

    #[\Override]
    protected function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setRequired(['id', 'ems_link', 'field_path'])
            ->setDefaults([
                'columns' => [['name' => 'structure', 'width' => 200]],
                'context' => [],
                'context_block' => null,
                'template' => null,
                'locale' => null,
                'active_item_id' => null,
            ])
            ->setNormalizer('ems_link', fn (Options $options, EMSLink|string $value): EMSLink => \is_string($value) ? EMSLink::fromText($value) : $value)
            ->setNormalizer('columns', function (Options $options, array $columns): array {
                $columns = \array_map(static fn (array $column): JsonMenuNestedColumn => new JsonMenuNestedColumn(
                    $column['name'],
                    $column['width'] ?? 200
                ), $columns);

                return [new JsonMenuNestedColumn('title'), ...$columns];
            })
            ->setAllowedTypes('columns', 'array')
            ->setAllowedTypes('context', 'array')
            ->setAllowedTypes('context_block', ['null', 'string'])
            ->setAllowedTypes('template', ['null', 'string']);

        return $resolver->resolve($options);
    }
}

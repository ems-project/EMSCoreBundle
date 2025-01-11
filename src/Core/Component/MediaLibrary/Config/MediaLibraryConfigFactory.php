<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary\Config;

use EMS\CoreBundle\Core\Config\AbstractConfigFactory;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaLibraryConfigFactory extends AbstractConfigFactory
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    /**
     * @param array{
     *   id: string,
     *   contentTypeName: string,
     *   fieldPath: string,
     *   fieldFolder: string,
     *   fieldFile: string,
     *   sort: array<string, MediaLibraryConfigSort>,
     *   template: ?string,
     *   context: array<string, mixed>,
     *   defaultValue: array<mixed>,
     *   searchSize: int,
     *   searchQuery: array<mixed>,
     *   searchFileQuery: array<mixed>,
     * } $options
     */
    #[\Override]
    public function create(string $hash, array $options): MediaLibraryConfig
    {
        $contentType = $this->contentTypeService->giveByName($options['contentTypeName']);

        $config = new MediaLibraryConfig(
            hash: $hash,
            id: (string) $options['id'],
            contentType: $contentType,
            fieldPath: $this->getField($contentType, $options['fieldPath'])->getName(),
            fieldFolder: $this->getField($contentType, $options['fieldFolder'])->getName(),
            fieldFile: $this->getField($contentType, $options['fieldFile'])->getName(),
            sort: $options['sort']
        );

        $config->defaultValue = $options['defaultValue'];
        $config->searchSize = $options['searchSize'];
        $config->searchQuery = $options['searchQuery'];
        $config->searchFileQuery = $options['searchFileQuery'];
        $config->template = $options['template'];
        $config->context = $options['context'];

        return $config;
    }

    #[\Override]
    protected function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                'contentTypeName' => 'media_file',
                'fieldPath' => 'media_path',
                'fieldFolder' => 'media_folder',
                'fieldFile' => 'media_file',
                'sort' => [
                    ['id' => 'name', 'field' => 'media_path.alpha_order', 'defaultOrder' => 'asc'],
                    ['id' => 'type', 'field' => 'media_file.mimetype', 'nested_path' => 'media_file'],
                    ['id' => 'size', 'field' => 'media_file.filesize', 'nested_path' => 'media_file'],
                ],
                'defaultValue' => [],
                'searchSize' => MediaLibraryConfig::DEFAULT_SEARCH_SIZE,
                'searchQuery' => [],
                'searchFileQuery' => MediaLibraryConfig::DEFAULT_SEARCH_FILE_QUERY,
                'context' => [],
                'template' => null,
            ])
            ->setRequired([
                'id',
                'contentTypeName',
                'fieldPath',
                'fieldFolder',
                'fieldFile',
            ])
            ->setAllowedTypes('sort', 'array')
            ->setAllowedTypes('defaultValue', 'array')
            ->setAllowedTypes('searchQuery', 'array')
            ->setAllowedTypes('searchFileQuery', 'array')
            ->setAllowedTypes('searchSize', 'int')
            ->setAllowedTypes('context', 'array')
            ->setNormalizer('sort', function (OptionsResolver $optionsResolver, array $definitions): array {
                $sorts = [];

                foreach ($definitions as $definition) {
                    $sorts[$definition['id']] = new MediaLibraryConfigSort(
                        id: $definition['id'],
                        field: $definition['field'],
                        defaultOrder: $definition['defaultOrder'] ?? null,
                        nestedPath: $definition['nested_path'] ?? null
                    );
                }

                return $sorts;
            })
        ;

        /** @var array{contentTypeName: string} $resolved */
        $resolved = $resolver->resolve($options);

        return $resolved;
    }

    private function getField(ContentType $contentType, string $name): FieldType
    {
        $field = $contentType->getFieldType()->getChildByName($name);

        return $field ?: throw new \RuntimeException(\vsprintf('Field "%s" not found in "%s" contentType', [$name, $contentType->getName()]));
    }
}

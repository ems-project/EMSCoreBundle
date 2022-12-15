<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Component\MediaLibrary;

use EMS\CoreBundle\Core\Config\AbstractConfigFactory;
use EMS\CoreBundle\Core\Config\ConfigFactoryInterface;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaLibraryConfigFactory extends AbstractConfigFactory implements ConfigFactoryInterface
{
    public function __construct(private readonly ContentTypeService $contentTypeService)
    {
    }

    /**
     * @param array{
     *   id: string,
     *   contentTypeName: string,
     *   field_path: string,
     *   field_location: string,
     *   field_file: string,
     *   field_alpha_order: ?string
     * } $options
     */
    public function create(string $hash, array $options): MediaLibraryConfig
    {
        $contentType = $this->contentTypeService->giveByName($options['contentTypeName']);

        return new MediaLibraryConfig(
            $hash,
            (string) $options['id'],
            $contentType,
            $this->getField($contentType, $options['field_path'])->getName(),
            $this->getField($contentType, $options['field_location'])->getName(),
            $this->getField($contentType, $options['field_file'])->getName(),
            $options['field_alpha_order'],
        );
    }

    private function getField(ContentType $contentType, string $name): FieldType
    {
        $field = $contentType->getFieldType()->getChildByName($name);

        return $field ?: throw new \RuntimeException(\vsprintf('Field "%s" not found in "%s" contentType', [$name, $contentType->getName()]));
    }

    /** {@inheritdoc} */
    protected function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                'field_path' => 'media_path',
                'field_location' => 'media_location',
                'field_file' => 'media_file',
                'field_alpha_order' => null,
            ])
            ->setRequired([
                'id',
                'contentTypeName',
            ]);

        /** @var array{contentTypeName: string} $resolved */
        $resolved = $resolver->resolve($options);

        return $resolved;
    }
}

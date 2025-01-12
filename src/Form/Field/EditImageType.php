<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\Form\AssetEntity;
use EMS\CoreBundle\Form\DataTransformer\AssetTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class EditImageType extends AbstractType
{
    public const FIELD_FILENAME = 'filename';
    public const FIELD_MIMETYPE = 'mimetype';
    public const FIELD_HASH = 'hash';
    public const FIELD_X = 'x';
    public const FIELD_Y = 'y';
    public const FIELD_WIDTH = 'width';
    public const FIELD_HEIGHT = 'height';
    public const FIELD_ROTATE = 'rotate';
    public const FIELD_SCALE_X = 'scale_x';
    public const FIELD_SCALE_Y = 'scale_y';
    public const FIELD_BACKGROUND_COLOR = 'background_color';

    public function __construct(
        private readonly AssetTransformer $transformer,
    ) {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->transformer);
        $builder->add(self::FIELD_FILENAME, HiddenType::class);
        $builder->add(self::FIELD_MIMETYPE, HiddenType::class);
        $builder->add(self::FIELD_HASH, HiddenType::class);
        $builder->add(self::FIELD_X, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-x',
            ],
        ]);
        $builder->add(self::FIELD_Y, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-y',
            ],
        ]);
        $builder->add(self::FIELD_WIDTH, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-width',
            ],
        ]);
        $builder->add(self::FIELD_HEIGHT, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-height',
            ],
        ]);
        $builder->add(self::FIELD_ROTATE, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-rotate',
            ],
        ]);
        $builder->add(self::FIELD_SCALE_X, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-scale-x',
            ],
        ]);
        $builder->add(self::FIELD_SCALE_Y, HiddenType::class, [
            'attr' => [
                'class' => 'ems-cropper-scale-y',
            ],
        ]);
        $builder->add(self::FIELD_BACKGROUND_COLOR, ColorPickerFullType::class, [
            'attr' => [
                'class' => 'ems-cropper-background-color',
            ],
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AssetEntity::class,
        ]);
    }
}

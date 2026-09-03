<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\View\Orientation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class OrientationType extends AbstractType
{
    public function getParent(): string
    {
        return EnumType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => t('field.orientation', [], 'emsco-core'),
            'class' => Orientation::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn (?string $value): ?Orientation => $value ? Orientation::from($value) : null,
            fn (?Orientation $orientation): ?string => $orientation?->value,
        ));
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CommonBundle\Json\JsonMenuNested;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\DataField\TextStringFieldType;
use EMS\CoreBundle\Form\DataTransformer\DataFieldModelTransformer;
use EMS\CoreBundle\Form\DataTransformer\DataFieldViewTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionJsonMenuNestedType extends AbstractType
{
    public function __construct(private readonly FormRegistryInterface $formRegistry)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FieldType $fieldType */
        $fieldType = $options['field_type'];
        /** @var ContentType $contentType */
        $contentType = $options['content_type'];
        /** @var ?JsonMenuNested $item */
        $item = $options['item'];

        $labelChild = $fieldType->getChildren()->filter(fn (FieldType $c) => 'label' === $c->getName());
        if (0 === $labelChild->count()) {
            $labelFieldType = new FieldType();
            $labelFieldType->setName('label');
            $labelFieldType->setType(TextStringFieldType::class);
            $labelFieldType->setOptions(['displayOptions' => ['label' => 'Label', 'class' => 'col-md-12']]);
            $fieldType->addChild($labelFieldType, true);
        }

        $builder->add('data', $fieldType->getType(), [
            'metadata' => $fieldType,
            'error_bubbling' => false,
            'disabled_fields' => $contentType->getDisabledDataFields(),
            'locale' => $options['locale'],
        ]);

        if ($item) {
            $builder->add('_item_hash', HiddenType::class, ['data' => $item->getObjectHash()]);
        }

        $builder->get('data')
            ->addModelTransformer(new DataFieldModelTransformer($fieldType, $this->formRegistry))
            ->addViewTransformer(new DataFieldViewTransformer($fieldType, $this->formRegistry));
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'raw_data' => [],
                'item' => null,
            ])
            ->setRequired(['field_type', 'content_type'])
            ->setAllowedTypes('field_type', FieldType::class)
            ->setAllowedTypes('content_type', ContentType::class)
            ->setAllowedTypes('item', ['null', JsonMenuNested::class])
        ;
    }
}

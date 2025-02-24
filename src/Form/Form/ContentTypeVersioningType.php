<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\Version\Versioning;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeVersioningType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tags', CollectionType::class, [
                'entry_type' => TextType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => 'Add version',
                    'data-lang-remove' => 'X',
                    'data-entry-remove-class' => 'btn btn-danger',
                ],
                'entry_options' => [
                    'label' => false,
                    'attr' => ['style' => 'width: 150px; float: left;'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'block_prefix' => 'tags',
            ])
            ->add('fields', ContentTypeVersionFieldsType::class, [
                'label' => false,
                'mapping' => $options['mapping'],
            ])
            ->add('options', ContentTypeVersionOptionsType::class);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Versioning::class,
            ])
            ->setRequired(['mapping']);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\ContentTypeSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeSettingsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(ContentTypeSettings::HIDE_REVISION_SIDEBAR, CheckboxType::class, ['required' => false])
            ->add(ContentTypeSettings::TASKS_ENABLED, CheckboxType::class, ['required' => false])
            ->add(ContentTypeSettings::TASKS_TITLES, CollectionType::class, [
                'entry_type' => TextType::class,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection',
                    'data-lang-add' => 'Add title',
                    'data-lang-remove' => 'X',
                    'data-entry-remove-class' => 'btn btn-danger',
                ],
                'entry_options' => [
                    'label' => false,
                    'attr' => ['style' => 'width: 350px; float: left;'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'block_prefix' => 'tags',
            ]);
    }
}

<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ContentType $contentType */
        $contentType = $builder->getData();
        $environment = $contentType->giveEnvironment();

        $mapping = $options['mapping'] ?? null;
        if (null !== $mapping) {
            $builder->add('sortOrder', ChoiceType::class, [
                'required' => false,
                'label' => 'Default sort order',
                'choices' => [
                    'Ascending' => 'asc',
                    'Descending' => 'desc',
                ],
            ]);

            if ($environment->getManaged()) {
                $builder
                    ->add('versionFields', ContentTypeVersionFieldsType::class, [
                        'label' => false,
                        'mapping' => $mapping,
                    ])
                    ->add('versionOptions', ContentTypeVersionOptionsType::class)
                    ->add('versionTags', CollectionType::class, [
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
                    ]);
            }
        }

        $builder->add('refererFieldName');
        $builder->add('editTwigWithWysiwyg', CheckboxType::class, [
            'label' => 'Edit the Twig template with a WYSIWYG editor',
            'required' => false,
        ]);
        $builder->add('webContent', CheckboxType::class, [
            'label' => 'Web content (available in WYSIWYG field as internal link)',
            'required' => false,
        ]);

        if ($environment->getManaged()) {
            $builder->add('autoPublish', CheckboxType::class, [
                'label' => 'Silently publish draft and auto-save into the default environment',
                'required' => false,
            ]);
        }

        $builder->add('singularName', TextType::class);
        $builder->add('pluralName', TextType::class);
        $builder->add('icon', IconPickerType::class, [
            'required' => false,
        ]);
        $builder->add('color', ColorPickerType::class, [
            'required' => false,
        ]);

        $builder->add('description', TextareaType::class, [
            'required' => false,
            'attr' => [
                'class' => 'ckeditor',
            ],
        ]);

        if ($options['twigWithWysiwyg']) {
            $builder->add('indexTwig', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'ckeditor',
                    'rows' => 10,
                ],
            ]);
        } else {
            $builder->add('indexTwig', CodeEditorType::class, [
                'required' => false,
                'attr' => [
                ],
                'slug' => 'content_type',
            ]);
        }

        $builder->add('extra', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => 10,
            ],
        ]);

        $builder->add('save', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);
        $builder->add('saveAndUpdateMapping', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);
        $builder->add('saveAndClose', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-sm ',
            ],
            'icon' => 'fa fa-save',
        ]);

        $builder->add('rootContentType');

        $builder->add('roles', ContentTypeRolesType::class, [
            'managed' => $environment->getManaged(),
            'label' => false,
        ]);
        $builder->add('settings', ContentTypeSettingsType::class, [
            'label' => false,
        ]);

        if (null !== $mapping) {
            $builder->add('fields', ContentTypeFieldsType::class, [
                'label' => false,
                'mapping' => $mapping,
            ]);
        }

        if ($environment->getManaged()) {
            $builder->add('defaultValue', CodeEditorType::class, [
                'required' => false,
            ])->add('askForOuuid', CheckboxType::class, [
                'label' => 'Ask for OUUID',
                'required' => false,
            ]);
            $builder->add('saveAndEditStructure', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
            $builder->add('saveAndReorder', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-reorder',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('twigWithWysiwyg', true);
        $resolver->setDefault('mapping', null);
    }
}

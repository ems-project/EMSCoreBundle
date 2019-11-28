<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\ContentTypeFieldPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeType extends AbstractType
{
    /**
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        /** @var ContentType $contentType */
        $contentType = $builder->getData();
        
        if (!empty($options['mapping']) && !empty(array_values($options['mapping'])[0]['mappings'][$options['data']->getName()]['properties'])) {
            $mapping = array_values($options['mapping'])[0]['mappings'][$options['data']->getName()]['properties'];
            $builder->add('labelField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'text',
                        'keyword',
                        'string',
                        'integer'
                ]]);
            
            $builder->add('colorField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'string',
                        'keyword',
                        'text',
                ]]);
            $builder->add('circlesField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'string',
                        'keyword',
                        'text',
                ]]);
            $builder->add('emailField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'string',
                        'keyword',
                        'text',
                ]]);
            $builder->add('categoryField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'string',
                        'keyword',
                        'text',
                ]]);
            $builder->add('imageField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'nested',
                ]]);
            $builder->add('assetField', ContentTypeFieldPickerType::class, [
                'required' => false,
                'firstLevelOnly' => true,
                'mapping' => $mapping,
                'types' => [
                        'nested',
                ]]);
            $builder->add('sortBy', ContentTypeFieldPickerType::class, [
                    'required' => false,
                    'firstLevelOnly' => false,
                    'mapping' => $mapping,
                    'types' => [
                            'keyword',
                            'date',
                            'integer',
                            'string', //TODO: backward compatibility with ES2 To remove?
                    ]]);
            $builder->add('sortOrder', ChoiceType::class, [
                    'required' => false,
                    'label' => 'Default sort order',
                    'choices'  => [
                        'Ascending' => 'asc',
                        'Descending' => 'desc',
                    ],
            ]);
        }
        
        

//         $builder->add ( 'parentField');
//         $builder->add ( 'userField');
//         $builder->add ( 'dateField');
//         $builder->add ( 'startDateField');
        $builder->add('refererFieldName');
        $builder->add('editTwigWithWysiwyg', CheckboxType::class, [
                'label' => 'Edit the Twig template with a WYSIWYG editor',
                'required' => false,
        ]);
        $builder->add('webContent', CheckboxType::class, [
            'label' => 'Web content (available in WYSIWYG field as internal link)',
            'required' => false,
        ]);
        $builder->add('autoPublish', CheckboxType::class, [
            'label' => 'Silently publish draft and auto-save into the default environment',
            'required' => false,
        ]);
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
                        'class' => 'ckeditor'
                ]
        ]);
        
        if ($options['twigWithWysiwyg']) {
            $builder->add('indexTwig', TextareaType::class, [
                    'required' => false,
                    'attr' => [
                            'class' => 'ckeditor',
                            'rows' => 10,
                    ]
            ]);
        } else {
            $builder->add('indexTwig', CodeEditorType::class, [
                    'required' => false,
                    'attr' => [
                    ],
                    'slug' => 'content_type'
            ]);
        }
        
        $builder->add('extra', TextareaType::class, [
                'required' => false,
                'attr' => [
                        'rows' => 10,
                ]
        ]);
        
        
        $builder->add('save', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save'
        ]);
        $builder->add('saveAndUpdateMapping', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save'
        ]);
        $builder->add('saveAndClose', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-save'
        ]);

        $builder->add('rootContentType');
        $builder->add('viewRole', RolePickerType::class);
        
        if ($contentType->getEnvironment()->getManaged()) {
            $builder->add('defaultValue', CodeEditorType::class, [
                'required' => false,
                'language' => 'ace/mode/json',
            ])->add('askForOuuid', CheckboxType::class, [
                'label' => 'Ask for OUUID',
                'required' => false,
            ]);
            $builder->add('createRole', RolePickerType::class);
            $builder->add('editRole', RolePickerType::class);
            $builder->add('publishRole', RolePickerType::class);
            $builder->add('trashRole', RolePickerType::class);
            $builder->add('orderField');
            $builder->add('saveAndEditStructure', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm '
                    ],
                    'icon' => 'fa fa-save'
            ]);
            $builder->add('saveAndReorder', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn-primary btn-sm '
                    ],
                    'icon' => 'fa fa-reorder'
            ]);
        }
        
        return parent::buildForm($builder, $options);
    }
    
    /**
     *
     * {@inheritdoc}
     *
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('twigWithWysiwyg', true);
        $resolver->setDefault('mapping', null);
    }
}

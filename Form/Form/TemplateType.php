<?php

namespace EMS\CoreBundle\Form\Form;

use Dompdf\Adapter\CPDF;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\ObjectPickerType;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Form\Field\CodeEditorType;

class TemplateType extends AbstractType
{
    
    private $choices;
    private $service;
    
    public function __construct($circleType, EnvironmentService $service)
    {
        $this->service = $service;
        $this->circleType = $circleType;
        $this->choices = null;
    }
    /**
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        
        /** @var Template $template */
        $template = $builder->getData();
        
        $builder
        ->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag'
        ])
        ->add('icon', IconPickerType::class, [
            'required' => false,
        ])
        ->add('public', CheckboxType::class, [
            'required' => false,
        ])
        ->add('editWithWysiwyg', CheckboxType::class, [
            'required' => false,
        ])
        ->add('preview', CheckboxType::class, [
            'required' => false,
            'label' => 'Preview',
        ])
        ->add('environments', ChoiceType::class, [
                'attr' => [
                    'class' => 'select2'
                ],
                 'multiple' => true,
                'choices' => $this->service->getAll(),
                'required' => false,
                'choice_label' => function ($value, $key, $index) {
                    return '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName();
                },
                'choice_value' => function ($value) {
                    if ($value != null) {
                        return $value->getId();
                    }
                    return $value;
                },
        ])
        ->add('role', RolePickerType::class)
        ->add('active', CheckboxType::class, [
                'required' => false,
                'label' => 'Active',
        ])
        
        ->add('renderOption', RenderOptionType::class, [
                'required' => true,
        ])
        ->add('accumulateInOneFile', CheckboxType::class, [
                'required' => false,
        ])
        ->add('mimeType', TextType::class, [
                'required' => false,
        ])
        ->add('emailContentType', TextType::class, [
                'required' => false,
                'label' => 'Content type (ie: text/html)',
        ])
        ->add('filename', CodeEditorType::class, [
                'required' => false,
                'attr' => [
                ],
                'slug' => 'template-filename',
                'max-lines' => 5,
                'min-lines' => 5,
        ])
        ->add('extension', TextType::class, [
                'required' => false,
        ])
        ->add('body', CodeEditorType::class, [
                'required' => false,
                'attr' => [
                ],
                'slug' => 'template-body'
        ])
        ->add('header', TextareaType::class, [
            'required' => false,
            'attr' => [
                'rows' => '10',
            ]
        ])
         ->add('roleCc', RolePickerType::class)
        ->add('roleTo', RolePickerType::class)
        ->add('circlesTo', ObjectPickerType::class, [
                'required' => false,
                'type' => $this->circleType,
                'multiple' => true,
        ])
        ->add('responseTemplate', CodeEditorType::class, [
            'required' => false,
            'attr' => [
            ],
            'slug' => 'template-response',
        ])->add('orientation', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Portrait' => 'portrait',
                    'Landscape' => 'landscape',
                ],
                'required' => false,
        ])->add('size', ChoiceType::class, [
            'required' => false,
            'choices' => array_combine(array_keys(CPDF::$PAPER_SIZES), array_keys(CPDF::$PAPER_SIZES)),
            'required' => false,
        ])->add('disposition', ChoiceType::class, [
            'label' => 'File diposition',
            'expanded' => true,
            'attr' => [
            ],
            'choices' => [
                'None' => null,
                'Attachment' => 'attachment',
                'Inline' => 'inline',
            ]
        ])
        ->add('allow_origin', TextType::class, [
            'label' => 'The Access-Control-Allow-Originm header',
            'required' => false,
            'attr' => [
            ],
        ])
        ->add('save', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn-primary btn-sm '
            ],
            'icon' => 'fa fa-save'
        ]);
    }
}

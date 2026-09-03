<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use Dompdf\Adapter\CPDF;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Form\Field\CodeEditorType;
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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ActionType extends AbstractType
{
    public function __construct(
        private readonly string $circleType,
        private readonly EnvironmentService $service
    ) {
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'ajax-save-url' => null,
            'attr' => ['class' => 'fields-to-display-by-value'],
        ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', IconTextType::class, [
                'label' => t('field.name', [], 'emsco-core'),
                'icon' => 'fa fa-tag',
                'row_attr' => ['class' => 'col-md-8'],
            ])
            ->add('label', IconTextType::class, [
                'label' => t('field.label', [], 'emsco-core'),
                'icon' => 'fa fa-header',
                'row_attr' => ['class' => 'col-md-8'],
            ])
            ->add('icon', IconPickerType::class, [
                'required' => false,
                'row_attr' => ['class' => 'col-md-4'],
            ])
            ->add('public', CheckboxType::class, [
                'label' => t('field.is_public', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('environments', ChoiceType::class, [
                'attr' => ['class' => 'select2'],
                'choices' => $this->service->getEnvironments(),
                'choice_label' => fn (Environment $value) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName(),
                'choice_value' => fn (Environment $value) => $value->getId(),
                'choice_translation_domain' => false,
                'label' => t('field.environments', [], 'emsco-core'),
                'multiple' => true,
                'required' => false,
                'row_attr' => ['class' => 'col-md-8'],
            ])
            ->add('body', CodeEditorType::class, [
                'label' => t('field.template_body', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'slug' => 'template-body',
            ])
            ->add('editWithWysiwyg', CheckboxType::class, [
                'label' => t('field.is_edit_wysiwyg', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => t('field.is_active', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('role', RolePickerType::class, [
                'label' => t('field.role', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-4'],
            ])
            ->add('renderOption', RenderOptionType::class, [
                'attr' => ['class' => 'fields-to-display-by-input-value'],
                'label' => t('field.render_option', [], 'emsco-core'),
                'required' => true,
                'row_attr' => ['class' => 'col-md-4'],
            ])
            ->add('header', TextareaType::class, [
                'attr' => [
                    'rows' => '10',
                    'class' => 'action_renderOption fields-to-display-for fields-to-display-for-embed',
                ],
                'label' => t('field.header', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('accumulateInOneFile', CheckboxType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-export'],
                'label' => t('field.accumulate_file', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'], ])
            ->add('spreadsheet', CheckboxType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-export'],
                'label' => t('field.spreadsheet', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'], ])
            ->add('mimeType', TextType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-export'],
                'label' => t('field.file.mimetype', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-6'], ])
            ->add('extension', TextType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-export'],
                'label' => t('field.file.extension', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-6'],
            ])
            ->add('roleTo', RolePickerType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-notification'],
                'label' => t('field.role_to', [], 'emsco-core'),
                'row_attr' => ['class' => 'col-md-6'],
            ])
            ->add('roleCc', RolePickerType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-notification'],
                'label' => t('field.role_cc', [], 'emsco-core'),
                'row_attr' => ['class' => 'col-md-6'],
            ])
            ->add('emailContentType', TextType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-notification'],
                'label' => t('field.email_content_type', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ]);

        if ('' !== $this->circleType) {
            $builder->add('circlesTo', ObjectPickerType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-notification'],
                'label' => t('field.circles_to', [], 'emsco-core'),
                'multiple' => true,
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'type' => $this->circleType,
            ]);
        }

        $builder
            ->add('responseTemplate', CodeEditorType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-notification'],
                'label' => t('field.template_response', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'slug' => 'template-response',
            ])
            ->add('tag', TextType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-job'],
                'label' => t('field.tag', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-6'],
            ])
            ->add('preview', CheckboxType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'label' => t('field.is_preview', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('disposition', ChoiceType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'label' => t('field.file.disposition', [], 'emsco-core'),
                'expanded' => true,
                'choices' => [
                    t('key.none', [], 'emsco-core')->getMessage() => null,
                    t('key.attachment', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    t('key.inline', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_INLINE,
                ],
                'choice_translation_domain' => 'emsco-core',
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('allow_origin', TextType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'label' => t('field.header_allow_origin', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('filename', CodeEditorType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'max-lines' => 5,
                'min-lines' => 5,
                'label' => t('field.file.name', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'slug' => 'template-filename',
            ])
            ->add('orientation', ChoiceType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'choices' => [
                    t('key.portrait', [], 'emsco-core')->getMessage() => 'portrait',
                    t('key.landscape', [], 'emsco-core')->getMessage() => 'landscape',
                ],
                'choice_translation_domain' => 'emsco-core',
                'label' => t('field.file.orientation', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('size', ChoiceType::class, [
                'attr' => ['class' => 'action_renderOption fields-to-display-for fields-to-display-for-pdf'],
                'required' => false,
                'choices' => \array_combine(\array_keys(CPDF::$PAPER_SIZES), \array_keys(CPDF::$PAPER_SIZES)),
                'label' => t('field.file.paper_size', [], 'emsco-core'),
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add('allowedRemoteHosts', CollectionType::class, [
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'class' => 'a2lix_lib_sf_collection action_renderOption fields-to-display-for fields-to-display-for-pdf',
                    'data-lang-add' => 'Add',
                    'data-lang-remove' => 'Remove',
                    'data-entry-remove-class' => 'btn btn-danger',
                ],
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['style' => 'width: 300px; float: left;'],
                ],
                'label' => t('field.allowed_remote_hosts', [], 'emsco-core'),
                'row_attr' => ['class' => 'col-md-12'],
            ])
        ;

        if (null !== $options['ajax-save-url']) {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-ajax-save-url' => $options['ajax-save-url'],
                    'data-testid' => 'btn-action-save',
                ],
                'icon' => 'fa fa-save',
            ])->add('save_close', SubmitEmsType::class, [
                'label' => t('action.save_close', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-testid' => 'btn-action-save-close',
                ],
                'icon' => 'fa fa-save',
            ]);
        } else {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => ['class' => 'btn btn-primary btn-sm', 'data-testid' => 'btn-action-save'],
                'icon' => 'fa fa-save',
            ]);
        }
    }
}

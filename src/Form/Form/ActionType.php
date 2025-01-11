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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActionType extends AbstractType
{
    public function __construct(
        private readonly string $circleType,
        private readonly EnvironmentService $service)
    {
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('ajax-save-url', null);
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', IconTextType::class, ['icon' => 'fa fa-tag'])
            ->add('label', IconTextType::class, ['icon' => 'fa fa-header'])
            ->add('icon', IconPickerType::class, ['required' => false])
            ->add('public', CheckboxType::class, ['required' => false])
            ->add('editWithWysiwyg', CheckboxType::class, ['required' => false])
            ->add('preview', CheckboxType::class, ['required' => false, 'label' => 'Preview'])
            ->add('environments', ChoiceType::class, [
                'attr' => ['class' => 'select2'],
                'multiple' => true,
                'choices' => $this->service->getEnvironments(),
                'required' => false,
                'choice_label' => fn (Environment $value) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName(),
                'choice_value' => fn (Environment $value) => $value->getId(),
            ])
            ->add('role', RolePickerType::class)
            ->add('active', CheckboxType::class, ['required' => false, 'label' => 'Active'])
            ->add('renderOption', RenderOptionType::class, ['required' => true])
            ->add('accumulateInOneFile', CheckboxType::class, ['required' => false])
            ->add('spreadsheet', CheckboxType::class, ['required' => false])
            ->add('mimeType', TextType::class, ['required' => false])
            ->add('emailContentType', TextType::class, ['required' => false, 'label' => 'Content type (ie: text/html)'])
            ->add('filename', CodeEditorType::class, [
                'required' => false,
                'slug' => 'template-filename',
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add('extension', TextType::class, ['required' => false])
            ->add('body', CodeEditorType::class, ['required' => false, 'slug' => 'template-body'])
            ->add('header', TextareaType::class, ['required' => false, 'attr' => ['rows' => '10']])
            ->add('roleCc', RolePickerType::class)
            ->add('roleTo', RolePickerType::class)
            ->add('responseTemplate', CodeEditorType::class, [
                'required' => false,
                'slug' => 'template-response',
            ])
            ->add('orientation', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Portrait' => 'portrait',
                    'Landscape' => 'landscape',
                ],
            ])
            ->add('size', ChoiceType::class, [
                'required' => false,
                'choices' => \array_combine(\array_keys(CPDF::$PAPER_SIZES), \array_keys(CPDF::$PAPER_SIZES)),
            ])
            ->add('disposition', ChoiceType::class, [
                'label' => 'File diposition',
                'expanded' => true,
                'choices' => [
                    'None' => null,
                    'Attachment' => 'attachment',
                    'Inline' => 'inline',
                ],
            ])
            ->add('allow_origin', TextType::class, [
                'label' => 'The Access-Control-Allow-Originm header',
                'required' => false,
            ])
            ->add('tag', TextType::class, ['required' => false])
            ->add('saveAndClose', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm '],
                'icon' => 'fa fa-save',
            ])
        ;

        if ('' !== $this->circleType) {
            $builder->add('circlesTo', ObjectPickerType::class, [
                'required' => false,
                'type' => $this->circleType,
                'multiple' => true,
            ]);
        }

        if ($options['ajax-save-url']) {
            $builder->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                    'data-ajax-save-url' => $options['ajax-save-url'],
                ],
                'icon' => 'fa fa-save',
            ]);
        }
    }
}

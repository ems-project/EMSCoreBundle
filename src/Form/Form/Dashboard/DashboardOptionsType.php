<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\Dashboard\Services\DashboardInterface;
use EMS\CoreBundle\Core\Dashboard\Services\Export;
use EMS\CoreBundle\Core\Dashboard\Services\Template;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DashboardOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dashboard = $options['dashboard'];

        if ($dashboard instanceof Export || $dashboard instanceof Template) {
            $builder->add(DashboardOptions::BODY, CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ]);
        }

        match ($dashboard::class) {
            Export::class => $this->buildForExport($builder) ,
            Template::class => $this->buildForTemplate($builder),
            default => null,
        };
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     */
    private function buildForTemplate(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::HEADER, CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FOOTER, CodeEditorType::class, [
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ]);
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     */
    private function buildForExport(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::FILENAME, CodeEditorType::class, [
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add(DashboardOptions::MIMETYPE, null, [
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FILE_DISPOSITION, ChoiceType::class, [
                'expanded' => true,
                'row_attr' => ['class' => 'col-md-12'],
                'choices' => [
                    'dashboard.export.none' => null,
                    'dashboard.export.attachment' => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'dashboard.export.inline' => ResponseHeaderBag::DISPOSITION_INLINE,
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'label' => false,
                'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            ])
            ->setNormalizer('label_format', fn (Options $options) => 'dashboard.'.\strtolower((new \ReflectionClass($options['dashboard']))->getShortName()).'.%name%'
            )
            ->setRequired(['dashboard'])
            ->setAllowedTypes('dashboard', DashboardInterface::class)
        ;
    }
}

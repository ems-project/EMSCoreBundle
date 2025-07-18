<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form\Dashboard;

use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\Dashboard\Services\DashboardInterface;
use EMS\CoreBundle\Core\Dashboard\Services\Export;
use EMS\CoreBundle\Core\Dashboard\Services\Template;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class DashboardOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dashboard = $options['dashboard'];

        if ($dashboard instanceof Export || $dashboard instanceof Template) {
            $builder->add(DashboardOptions::BODY, CodeEditorType::class, [
                'label' => t('field.template_body', [], 'emsco-core'),
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
     * @param FormBuilderInterface<mixed> $builder
     */
    private function buildForTemplate(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::HEADER, CodeEditorType::class, [
                'label' => t('field.template_header', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FOOTER, CodeEditorType::class, [
                'label' => t('field.template_footer', [], 'emsco-core'),
                'required' => true,
                'language' => 'ace/mode/twig',
                'row_attr' => ['class' => 'col-md-12'],
            ]);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    private function buildForExport(FormBuilderInterface $builder): void
    {
        $builder
            ->add(DashboardOptions::FILENAME, CodeEditorType::class, [
                'label' => t('field.file.name', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
                'max-lines' => 5,
                'min-lines' => 5,
            ])
            ->add(DashboardOptions::MIMETYPE, null, [
                'label' => t('field.file.mimetype', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-12'],
            ])
            ->add(DashboardOptions::FILE_DISPOSITION, ChoiceType::class, [
                'label' => t('field.file.disposition', [], 'emsco-core'),
                'expanded' => true,
                'row_attr' => ['class' => 'col-md-12'],
                'choices' => [
                    t('key.none', [], 'emsco-core')->getMessage() => null,
                    t('key.attachment', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    t('key.inline', [], 'emsco-core')->getMessage() => ResponseHeaderBag::DISPOSITION_INLINE,
                ],
                'choice_translation_domain' => 'emsco-core',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'label' => false,
            ])
            ->setNormalizer(
                'label_format',
                fn (Options $options) => 'dashboard.'.\strtolower(new \ReflectionClass($options['dashboard'])->getShortName()).'.%name%'
            )
            ->setRequired(['dashboard'])
            ->setAllowedTypes('dashboard', DashboardInterface::class)
        ;
    }
}

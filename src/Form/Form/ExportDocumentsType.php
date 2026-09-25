<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Form\ExportDocuments;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ExportDocumentsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ExportDocuments $data */
        $data = $builder->getData();

        $formatChoices = ['JSON' => 'json'];
        /** @var Template $template */
        foreach ($data->getContentType()->getTemplates() as $template) {
            if (RenderOptionType::EXPORT == $template->getRenderOption() && $template->getBody()) {
                $formatChoices[$template->getLabel()] = $template->getName();
            }
        }

        $builder
            ->setAction($data->getAction())
            ->add('query', HiddenType::class, [
                'label' => t('field.query', [], 'emsco-core'),
                'data' => $data->getQuery(),
            ])
            ->add('format', ChoiceType::class, [
                'label' => t('field.format', [], 'emsco-core'),
                'choice_translation_domain' => false,
                'choices' => $formatChoices,
            ])
            ->add('environment', EnvironmentPickerType::class, [
                'label' => t('field.environment', [], 'emsco-core'),
            ])
            ->add('withBusinessKey', CheckboxType::class, [
                'label' => t('field.with_business_key', [], 'emsco-core'),
                'data' => true,
                'required' => false,
            ])
            ->add('export', SubmitEmsType::class, [
                'label' => t('action.export_content_type', [
                    'pluralName' => $data->getContentType()->getPluralName(),
                ], 'emsco-core'),
                'attr' => ['class' => 'btn btn-primary btn-sm ', 'data-testid' => 'btn-action-export'],
                'icon' => 'fa fa-archive',
            ]);
    }
}

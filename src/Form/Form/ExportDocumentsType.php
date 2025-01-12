<?php

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

        $formatChoices = ['JSON export' => 'json'];
        /** @var Template $template */
        foreach ($data->getContentType()->getTemplates() as $template) {
            if (RenderOptionType::EXPORT == $template->getRenderOption() && $template->getBody()) {
                $formatChoices[$template->getLabel()] = $template->getName();
            }
        }

        $builder
            ->setAction($data->getAction())
            ->add('query', HiddenType::class, [
                'data' => $data->getQuery(),
            ])
            ->add('format', ChoiceType::class, [
                'choices' => $formatChoices,
            ])
            ->add('environment', EnvironmentPickerType::class, [
            ])
            ->add('withBusinessKey', CheckboxType::class, [
                'data' => true,
                'required' => false,
            ])
            ->add('export', SubmitEmsType::class, [
                'label' => 'Export '.$data->getContentType()->getPluralName(),
                'attr' => ['class' => 'btn btn-primary btn-sm '],
                'icon' => 'fa fa-archive',
            ]);
    }
}

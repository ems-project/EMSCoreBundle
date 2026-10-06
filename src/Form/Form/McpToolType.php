<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\McpTool;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class McpToolType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', null, [
                'col' => 3,
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
            ])
            ->add('name', null, [
                'col' => 3,
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => t('field.enabled', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('role', RolePickerType::class, [
                'col' => 3,
            ])
            ->add('description', TextareaType::class, [
                'label' => t('field.description', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('input_schema', CodeEditorType::class, [
                'label' => t('field.mcp_input_schema', [], 'emsco-core'),
                'required' => false,
                'language' => 'ace/mode/twig',
            ])
            ->add('output_schema', CodeEditorType::class, [
                'label' => t('field.mcp_output_schema', [], 'emsco-core'),
                'required' => false,
                'language' => 'ace/mode/twig',
            ])
            ->add('response', CodeEditorType::class, [
                'label' => t('field.mcp_response', [], 'emsco-core'),
                'required' => false,
                'language' => 'ace/mode/twig',
            ])
            ->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => ['data-testid' => 'btn-action-save'],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => McpTool::class,
        ]);
    }
}

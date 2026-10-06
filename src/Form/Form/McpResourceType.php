<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\McpResource;
use EMS\CoreBundle\Form\Field\CodeEditorType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
final class McpResourceType extends AbstractType
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
                'label' => t('field.label', [], 'emsco-core'),
                'required' => true,
                'col' => 3,
            ])
            ->add('name', null, [
                'label' => t('field.name', [], 'emsco-core'),
                'required' => true,
                'col' => 3,
            ])
            ->add('uri', TextType::class, [
                'label' => t('field.uri', [], 'emsco-core'),
                'required' => true,
                'col' => 6,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => t('field.enabled', [], 'emsco-core'),
                'required' => false,
            ])
            ->add('role', RolePickerType::class, [
                'mapped' => true,
                'col' => 6,
            ])
            ->add('mimeType', TextType::class, [
                'label' => t('field.mime_type', [], 'emsco-core'),
                'required' => true,
                'col' => 6,
            ])
            ->add('description', TextareaType::class, [
                'label' => t('field.description', [], 'emsco-core'),
                'required' => false,
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
            'data_class' => McpResource::class,
        ]);
    }
}

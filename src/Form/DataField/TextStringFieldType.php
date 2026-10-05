<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

use function Symfony\Component\Translation\t;

/**
 * Basic content type for text (regular text input).
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TextStringFieldType extends DataFieldType
{
    #[\Override]
    public function generateMcpSchema(FieldType $fieldType, callable $buildObjectSchema, bool $isOutputSchema = false): array
    {
        return ['type' => 'string'];
    }

    #[\Override]
    public function getLabel(): TranslatableMessage
    {
        return t('field_type.text', [], 'emsco-core');
    }

    /**
     * @param FormInterface<mixed> $form
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['class'] = null;
        $view->vars['attr']['placeholder'] = $options['placeholder'];
    }

    #[\Override]
    public static function getIcon(): string
    {
        return 'fa fa-pencil-square-o';
    }

    #[\Override]
    public function getParent(): string
    {
        return IconTextType::class;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'prefixIcon' => null,
            'prefixText' => null,
            'suffixIcon' => null,
            'suffixText' => null,
            'placeholder' => null,
        ]);
    }

    #[\Override]
    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
            'label' => t('field.icon', [], 'emsco-core'),
            'required' => false,
        ])->add('prefixIcon', IconPickerType::class, [
            'label' => t('field.prefix_icon', [], 'emsco-core'),
            'required' => false,
        ])->add('prefixText', IconTextType::class, [
            'label' => t('field.prefix_text', [], 'emsco-core'),
            'required' => false,
            'prefixIcon' => 'fa fa-hand-o-left',
        ])->add('suffixIcon', IconPickerType::class, [
            'label' => t('field.suffix_icon', [], 'emsco-core'),
            'required' => false,
        ])->add('suffixText', IconTextType::class, [
            'label' => t('field.suffix_text', [], 'emsco-core'),
            'required' => false,
            'prefixIcon' => 'fa fa-hand-o-right',
        ])->add('placeholder', TextType::class, [
            'label' => t('field.placeholder', [], 'emsco-core'),
            'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            // String specific mapping options
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class, [
                    'label' => t('field.analyzer', [], 'emsco-core'), ])
                ->add('copy_to', TextType::class, [
                    'label' => t('field.copy_to', [], 'emsco-core'),
                    'required' => false,
                ]);
        }
    }
}

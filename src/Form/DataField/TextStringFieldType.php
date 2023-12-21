<?php

namespace EMS\CoreBundle\Form\DataField;

use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\Field\AnalyzerPickerType;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Basic content type for text (regular text input).
 *
 * @author Mathieu De Keyzer <ems@theus.be>
 */
class TextStringFieldType extends DataFieldType
{
    public function getLabel(): string
    {
        return 'Text field';
    }

    /**
     * @param FormInterface<FormInterface> $form
     * @param array<string, mixed>         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['class'] = null;
        $view->vars['attr']['placeholder'] = $options['placeholder'];
    }

    //     /**
    //      *
    //      * {@inheritDoc}
    //      * @see \EMS\CoreBundle\Form\DataField\DataFieldType::viewTransform()
    //      */
    //     public function viewTransform(DataField $data){
    //         $out = parent::viewTransform($data);
    //         if(empty($out)) {
    //             return "";
    //         }
    //         return $out;
    //     }

    public static function getIcon(): string
    {
        return 'fa fa-pencil-square-o';
    }

    public function getParent(): string
    {
        return IconTextType::class;
    }

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

    public function buildOptionsForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildOptionsForm($builder, $options);
        $optionsForm = $builder->get('options');

        // String specific display options
        $optionsForm->get('displayOptions')->add('icon', IconPickerType::class, [
                'required' => false,
        ])->add('prefixIcon', IconPickerType::class, [
                'required' => false,
        ])->add('prefixText', IconTextType::class, [
                'required' => false,
                'prefixIcon' => 'fa fa-hand-o-left',
        ])->add('suffixIcon', IconPickerType::class, [
                'required' => false,
        ])->add('suffixText', IconTextType::class, [
                'required' => false,
                'prefixIcon' => 'fa fa-hand-o-right',
        ])->add('placeholder', TextType::class, [
                'required' => false,
        ]);

        if ($optionsForm->has('mappingOptions')) {
            // String specific mapping options
            $optionsForm->get('mappingOptions')
                ->add('analyzer', AnalyzerPickerType::class)
                ->add('copy_to', TextType::class, [
                    'required' => false,
                ]);
        }
    }
}

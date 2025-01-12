<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\Form\FormManager;
use EMS\CoreBundle\Entity\Form;
use EMS\CoreBundle\Form\DataTransformer\EntityNameModelTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormPickerType extends ChoiceType
{
    public function __construct(private readonly FormManager $formManager)
    {
        parent::__construct();
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $options['choices'] = $this->formManager->getAll();
        $builder->addModelTransformer(new EntityNameModelTransformer($this->formManager, $options['multiple']));
        parent::buildForm($builder, $options);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'attr' => [
                'class' => 'select2',
            ],
            'choice_label' => fn (Form $form) => \sprintf('<span><i class="fa fa-keyboard-o"></i>&nbsp;%s', $form->getLabel()),
            'choice_value' => function ($value) {
                if ($value instanceof Form) {
                    return $value->getName();
                }

                return $value;
            },
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFieldPickerType extends SelectPickerType
{
    /** @var ChoiceListFactoryInterface */
    private $choiceListFactory;

    public function __construct(ChoiceListFactoryInterface $factory)
    {
        $this->choiceListFactory = $factory;
        parent::__construct($factory);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /* set the default option value for this kind of compound field */
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'firstLevelOnly' => false,
            'types' => [],
            'mapping' => [],

            'choice_loader' => function (Options $options) {
                return $this->choiceListFactory->createLoader($options->offsetGet('mapping'), $options->offsetGet('types'), $options->offsetGet('firstLevelOnly'));
            },
            'choice_label' => function ($value, $key, $index) {
                return $value->getLabel();
            },
            'group_by' => function ($value, $key, $index) {
                return null;
            },
            'choice_value' => function ($value) {
                return $value->getValue();
            },
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}

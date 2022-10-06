<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\Factory\ContentTypeFieldChoiceListFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFieldPickerType extends SelectPickerType
{
    private ContentTypeFieldChoiceListFactory $choiceListFactory;

    public function __construct(ContentTypeFieldChoiceListFactory $factory)
    {
        parent::__construct($factory);
        $this->choiceListFactory = $factory;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'firstLevelOnly' => false,
            'types' => [],
            'mapping' => [],

            'choice_loader' => function (Options $options) {
                return $this->choiceListFactory->createLoader($options['mapping'], $options['types'], $options['firstLevelOnly']);
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

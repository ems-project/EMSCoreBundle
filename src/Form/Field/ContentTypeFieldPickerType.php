<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Form\Factory\ContentTypeFieldChoiceListFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypeFieldPickerType extends Select2Type
{
    private readonly ContentTypeFieldChoiceListFactory $choiceListFactory;

    public function __construct(ContentTypeFieldChoiceListFactory $factory)
    {
        parent::__construct($factory);
        $this->choiceListFactory = $factory;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'firstLevelOnly' => false,
            'types' => [],
            'mapping' => [],

            'choice_loader' => fn (Options $options) => $this->choiceListFactory->createLoader($options['mapping'], $options['types'], $options['firstLevelOnly']),
            'choice_label' => fn ($value, $key, $index) => $value->getLabel(),
            'group_by' => fn ($value, $key, $index) => null,
            'choice_value' => fn ($value) => $value->getValue(),
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}

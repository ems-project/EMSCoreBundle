<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Text;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WysiwygStylesSetPickerType extends ChoiceType
{
    public function __construct(private readonly WysiwygStylesSetService $stylesSetService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $choices = $this->getExistingStylesSets();

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => [
                'data-live-search' => true,
                'class' => 'wysiwyg-profile-picker select2',
            ],
            'choice_value' => fn ($value) => $value,
            'choice_translation_domain' => false,
            'choice_attr' => fn ($key) => [
                'data-icon' => 'fa fa-brands fa-css3',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getExistingStylesSets(): array
    {
        $out = [];
        $stylesSets = $this->stylesSetService->getStylesSets();

        foreach ($stylesSets as $stylesSet) {
            $out[Text::humanize($stylesSet->getName())] = $stylesSet->getName();
        }

        return $out;
    }
}

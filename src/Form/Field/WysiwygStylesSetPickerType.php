<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Text;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WysiwygStylesSetPickerType extends Select2Type
{
    public function __construct(private readonly WysiwygStylesSetService $stylesSetService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = $this->getExistingStylesSets();

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => [
                'data-live-search' => true,
                'class' => 'wysiwyg-profile-picker',
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

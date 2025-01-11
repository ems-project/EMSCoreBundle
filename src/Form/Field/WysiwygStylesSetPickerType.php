<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
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
            'choice_attr' => fn ($category, $key, $index) => // TODO: it would be nice to translate the roles
[
    'data-content' => "<div class='text-".$category."'><i class='fa fa-css3'></i>&nbsp;&nbsp;".$key.'</div>',
],
            'choice_value' => fn ($value) => $value,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getExistingStylesSets(): array
    {
        $out = [];
        $stylesSets = $this->stylesSetService->getStylesSets();

        $out['default'] = 'Default';

        foreach ($stylesSets as $stylesSet) {
            $out[$stylesSet->getName()] = $stylesSet->getName();
        }

        return $out;
    }
}

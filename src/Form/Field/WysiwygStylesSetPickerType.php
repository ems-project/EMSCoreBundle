<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WysiwygStylesSetPickerType extends SelectPickerType
{
    private WysiwygStylesSetService $stylesSetService;

    public function __construct(WysiwygStylesSetService $stylesSetService)
    {
        parent::__construct();
        $this->stylesSetService = $stylesSetService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = $this->getExistingStylesSets();

        $resolver->setDefaults([
            'choices' => $choices,
            'attr' => [
                'data-live-search' => true,
                'class' => 'wysiwyg-profile-picker',
            ],
            'choice_attr' => function ($category, $key, $index) {
                // TODO: it would be nice to translate the roles
                return [
                        'data-content' => "<div class='text-".$category."'><i class='fa fa-css3'></i>&nbsp;&nbsp;".$key.'</div>',
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getExistingStylesSets(): array
    {
        $stylesSets = $this->stylesSetService->getStylesSets();

        $out['default'] = 'Default';

        foreach ($stylesSets as $stylesSet) {
            $out[$stylesSet->getName()] = $stylesSet->getName();
        }

        return $out;
    }
}

<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\ContentType\ViewTypes;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ViewTypePickerType extends Select2Type
{
    public function __construct(private readonly ViewTypes $viewTypes)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->viewTypes->getIds(),
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $id) {
                $viewType = $this->viewTypes->get($id);

                return [
                    'data-content' => "<div class='text-".$category."'><i class='fa fa-square'></i>&nbsp;&nbsp;".$viewType->getLabel().'</div>',
                ];
            },
            'choice_value' => fn ($value) => $value,
        ]);
    }
}

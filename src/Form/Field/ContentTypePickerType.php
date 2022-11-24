<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContentTypePickerType extends ChoiceType
{
    /** @var array<mixed> */
    private array $choices = [];
    private ContentTypeService $service;

    public function __construct(ContentTypeService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function getBlockPrefix(): string
    {
        return 'selectpicker';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $this->choices = [];
        $keys = [];
        /** @var ContentType $choice */
        foreach ($this->service->getAll() as $choice) {
            $keys[] = $choice->getName();
            $this->choices[$choice->getName()] = $choice;
        }
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'choices' => $keys,
            'attr' => [
                    'data-live-search' => false,
            ],
            'choice_attr' => function ($category, $key, $index) {
                /** @var ContentType $contentType */
                $contentType = $this->choices[$index];

                return [
                        'data-content' => '<span class="text-'.$contentType->getColor().'"><i class="'.(empty($contentType->getIcon()) ? ' fa fa-book' : $contentType->getIcon()).'"></i>&nbsp;&nbsp;'.$contentType->getPluralName().'</span>',
                ];
            },
            'choice_value' => fn ($value) => $value,
            'multiple' => false,
            'choice_translation_domain' => false,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Repository\GroupRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class GroupPickerType extends ChoiceType
{
    public function __construct(private readonly GroupRepository $repository)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $choices = [];

        foreach ($this->repository->findBy([], ['label' => 'asc']) as $group) {
            $choices[$group->getLabel()] = $group->getName();
        }

        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'label' => t('field.groups', [], 'emsco-core'),
            'choice_translation_domain' => false,
            'choices' => $choices,
            'attr' => [
                'class' => 'select2',
            ],
        ]);
    }
}

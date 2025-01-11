<?php

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Service\AliasService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlignIndexesType extends Select2Type
{
    public function __construct(private readonly AliasService $aliasService)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $managedAliases = $this->aliasService->getManagedAliases();
        $choices = [];

        foreach ($managedAliases as $managedAlias) {
            /* @var $managedAlias ManagedAlias */
            $choices[$managedAlias->getName()] = $managedAlias;
        }

        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'multiple' => false,
            'label' => 'Align with:',
            'placeholder' => 'select managed alias',
            'choices' => \array_keys($choices),
            'choice_attr' => function ($name) use ($choices) {
                $managedAlias = $choices[$name];

                return [
                    'data-indexes' => '["'.\implode('", "', \array_keys($managedAlias->getIndexes())).'"]',
                    'data-content' => '<span class="text-'.$managedAlias->getColor().'"><i class="fa fa-code-fork"></i>&nbsp;&nbsp;'.$managedAlias->getName().'</span>',
                ];
            },
        ]);
    }
}

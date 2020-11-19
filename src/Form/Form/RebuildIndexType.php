<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class RebuildIndexType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options = [
            'A new index will be created and all objects will be reindexed with the revision defined for this environment. Once it\'s done the environement alias will be updated. Nothing will be removed from the current search index.' => 'newIndex',
            'All object in eMS will be just reindexed into the existing index.' => 'sameIndex',
        ];
        $builder
        ->add('option', ChoiceType::class, [
                'choices' => $options,
                'expanded' => true,
        ])
         ->add('rebuild', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-recycle',
         ]);
    }
}

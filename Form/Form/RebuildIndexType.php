<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\Revision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use EMS\CoreBundle\Form\Field\SubmitEmsType;

class RebuildIndexType extends AbstractType
{
    /**
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $options = [
            'A new index will be created and all objects will be reindexed with the revision defined for this environment. Once it\'s done the environement alias will be updated. Nothing will be removed from the current search index.' => 'newIndex',
            'All object in eMS will be just reindexed into the existing index.' => 'sameIndex',
        ];
        $builder
        ->add('option', ChoiceType::class, [
                'choices' => $options,
                'expanded' => true
        ])
         ->add('rebuild', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-sm '
                ],
                'icon' => 'fa fa-recycle'
         ]);
    }
}

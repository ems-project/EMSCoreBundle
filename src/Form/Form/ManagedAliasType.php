<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Field\AlignIndexesType;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\AliasService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManagedAliasType extends AbstractType
{
    /**
     * @var AliasService
     */
    private $aliasService;

    public function __construct(AliasService $aliasService)
    {
        $this->aliasService = $aliasService;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /* @var $data ManagedAlias */
        $data = $builder->getData();

        $builder
            ->add('name', IconTextType::class, ['icon' => 'fa fa-tag'])
            ->add('color', ColorPickerType::class, ['required' => false])
            ->add('extra', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '6'],
            ])
            ->add('indexes', ChoiceType::class, [
                'data' => \array_keys($data->getIndexes()),
                'choices' => \array_keys($options['indexes']),
                'choice_label' => function ($val) {
                    return $val;
                },
                'choice_attr' => function ($val, $key, $index) use ($options) {
                    return [
                        'class' => 'align-index',
                        'data-count' => $options['indexes'][$val]['count'],
                    ];
                },
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('align_indexes', AlignIndexesType::class)
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn-primary btn-sm '],
                'icon' => 'fa fa-save',
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ManagedAlias::class,
            'indexes' => $this->aliasService->build()->getAllIndexes(),
        ]);
    }
}

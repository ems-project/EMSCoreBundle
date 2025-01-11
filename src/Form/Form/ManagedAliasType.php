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
    public function __construct(private readonly AliasService $aliasService)
    {
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ManagedAlias $data */
        $data = $builder->getData();

        $builder
            ->add('name', IconTextType::class, ['icon' => 'fa fa-tag'])
            ->add('label', IconTextType::class, ['icon' => 'fa fa-header'])
            ->add('color', ColorPickerType::class, ['required' => false])
            ->add('extra', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '6'],
            ])
            ->add('indexes', ChoiceType::class, [
                'data' => \array_keys($data->getIndexes()),
                'choices' => \array_keys($options['indexes']),
                'choice_label' => fn ($val) => $val,
                'choice_attr' => fn ($val, $key, $index) => [
                    'class' => 'align-index',
                    'data-count' => $options['indexes'][$val]['count'],
                ],
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
            ])
            ->add('align_indexes', AlignIndexesType::class)
            ->add('save', SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-primary btn-sm '],
                'icon' => 'fa fa-save',
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ManagedAlias::class,
            'indexes' => $this->aliasService->build()->getAllIndexes(),
        ]);
    }
}

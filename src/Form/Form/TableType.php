<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Twig\Table\TableAction;
use EMS\CoreBundle\Twig\Table\TableInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TableType extends AbstractType
{
    public const REORDER_ACTION = 'reorderAction';

    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'] ?? null;
        if (!$data instanceof TableInterface) {
            throw new \RuntimeException('Unexpected TableInterface type');
        }
        $choices = [];
        foreach ($data as $id => $row) {
            $choices[$id] = $id;
        }

        $builder->add('selected', ChoiceType::class, [
            'choices' => $choices,
            'choice_label' => function ($choice, $key, $value) {
                return false;
            },
            'expanded' => true,
            'multiple' => true,
            'label' => false,
        ]);
        if ($data->isSortable()) {
            $builder->add('reordered', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [],
                'data' => $choices,
            ])->add(self::REORDER_ACTION, SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-danger',
                ],
                'icon' => 'fa fa-reorder',
                'label' => 'table.index.button.reorder',
            ]);
        }
        /** @var TableAction $action */
        foreach ($data->getTableActions() as $action) {
            $builder
                ->add($action->getName(), SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn-danger',
                    ],
                    'icon' => $action->getIcon(),
                    'label' => $action->getLabelKey(),
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TableInterface::class,
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}

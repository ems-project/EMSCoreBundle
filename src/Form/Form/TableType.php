<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Data\TableAction;
use EMS\CoreBundle\Form\Data\TableInterface;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

final class TableType extends AbstractType
{
    public const REORDER_ACTION = 'reorderAction';

    /**
     * @return string[]
     */
    public static function getReorderedKeys(string $formName, Request $request): array
    {
        $newOrder = [];
        foreach ($request->get($formName, [])['reordered'] ?? [] as $id) {
            if (!\is_string($id)) {
                throw new \RuntimeException('Unexpected type for id');
            }
            $newOrder[] = $id;
        }

        return $newOrder;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
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

        if ($data->supportsTableActions()) {
            $builder->add('selected', ChoiceType::class, [
                'choices' => $choices,
                'choice_label' => fn ($choice, $key, $value) => false,
                'expanded' => true,
                'multiple' => true,
                'label' => false,
            ]);
        }

        if (0 === $data->count()) {
            return;
        }

        if ($data->isSortable() && $data->count() > 1) {
            $builder->add('reordered', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'entry_options' => [],
                'data' => $choices,
            ])->add(self::REORDER_ACTION, SubmitEmsType::class, [
                'attr' => ['class' => 'btn btn-default'],
                'icon' => 'fa fa-reorder',
                'label' => t('action.reorder', [], 'emsco-core'),
            ]);
        }
        if ($data->supportsTableActions()) {
            /** @var TableAction $action */
            foreach ($data->getTableActions() as $action) {
                $submitOptions = ['icon' => $action->getIcon(), 'label' => $action->getLabelKey()];

                if ($confirmationKey = $action->getConfirmationKey()) {
                    $submitOptions['confirm'] = $confirmationKey;
                    $submitOptions['confirm_class'] = $action->getCssClass();
                } else {
                    $submitOptions['attr'] = ['class' => $action->getCssClass()];
                }

                $builder->add($action->getName(), SubmitEmsType::class, $submitOptions);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TableInterface::class,
            'reorder_label' => t('action.reorder', [], 'emsco-core'),
            'add_label' => t('action.add', [], 'emsco-core'),
            'title_label' => false,
        ]);
    }

    /**
     * @param FormInterface<AbstractType> $form
     * @param array<string, mixed>        $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
        $view->vars['reorder_label'] = $options['reorder_label'];
        $view->vars['add_label'] = $options['add_label'];
        $view->vars['title_label'] = $options['title_label'];
    }

    public function getBlockPrefix(): string
    {
        return 'emsco_form_table_type';
    }
}

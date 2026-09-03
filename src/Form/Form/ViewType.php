<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Field\IconPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\RolePickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Field\ViewTypePickerType;
use EMS\Helpers\Standard\Type;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * @extends AbstractType<mixed>
 */
class ViewType extends AbstractType
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var View $view */
        $view = $options['data'];

        $builder
            ->add('name', IconTextType::class, [
                'label' => t('field.name', [], 'emsco-core'),
                'icon' => 'fa fa-tag',
                'row_attr' => [
                    'class' => 'col-md-8',
                ],
            ])
            ->add('label', IconTextType::class, [
                'label' => t('field.label', [], 'emsco-core'),
                'icon' => 'fa fa-header',
                'row_attr' => [
                    'class' => 'col-md-8',
                ],
            ])
            ->add('icon', IconPickerType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('role', RolePickerType::class, [
                'label' => t('field.role', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-4',
                ],
            ])
            ->add('public', CheckboxType::class, [
                'label' => t('key.public_view', [], 'emsco-core'),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ]);

        if ($options['create']) {
            $builder->add('type', ViewTypePickerType::class, [
                'label' => t('field.type', [], 'emsco-core'),
                'required' => false,
                'row_attr' => ['class' => 'col-md-6'],
            ]);
        } else {
            $viewOptionsType = Type::string($this->container->get($view->getType())::class);
            if (!\is_subclass_of($viewOptionsType, FormTypeInterface::class)) {
                throw new \UnexpectedValueException();
            }

            $builder->add('options', $viewOptionsType, [
                'view' => $view,
                'row_attr' => ['class' => 'col-md-12'],
            ]);
        }

        if (null !== $options['ajax-save-url']) {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-ajax-save-url' => $options['ajax-save-url'],
                    'data-testid' => 'btn-action-save',
                ],
                'icon' => 'fa fa-save',
            ])->add('save_close', SubmitEmsType::class, [
                'label' => t('action.save_close', [], 'emsco-core'),
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                    'data-testid' => 'btn-action-save-close',
                ],
                'icon' => 'fa fa-save',
            ]);
        } else {
            $builder->add('save', SubmitEmsType::class, [
                'label' => t('action.save', [], 'emsco-core'),
                'attr' => ['class' => 'btn btn-primary btn-sm', 'data-testid' => 'btn-action-save'],
                'icon' => 'fa fa-save',
            ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => View::class,
            'create' => false,
            'ajax-save-url' => null,
        ]);
    }
}

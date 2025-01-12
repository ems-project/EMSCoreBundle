<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
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
use Symfony\Component\OptionsResolver\OptionsResolver;

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
        $view = $builder->getData();
        if (!$view instanceof View) {
            throw new \RuntimeException('Unexpected non View object');
        }

        $builder->add('name', IconTextType::class, [
            'icon' => 'fa fa-tag',
            'row_attr' => [
                'class' => 'col-md-8',
            ],
        ])
        ->add('label', IconTextType::class, [
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
            'required' => false,
            'row_attr' => [
                'class' => 'col-md-4',
            ],
        ])
        ->add('public', CheckboxType::class, [
            'required' => false,
            'row_attr' => [
                'class' => 'col-md-12',
            ],
        ]);

        if ($options['create']) {
            $builder->add('type', ViewTypePickerType::class, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])->add('create', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm',
                ],
                'icon' => 'fa fa-save',
            ]);
        } else {
            $builder->add('options', Type::string($this->container->get($view->getType())::class), [
                'view' => $view,
                'row_attr' => [
                    'class' => 'col-md-12',
                ],
            ])
                ->add('save', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn-primary btn-sm',
                        'data-ajax-save-url' => $options['ajax-save-url'],
                    ],
                    'icon' => 'fa fa-save',
                ])
                ->add('saveAndClose', SubmitEmsType::class, [
                    'attr' => [
                        'class' => 'btn-primary btn-sm',
                    ],
                    'icon' => 'fa fa-save',
                ]);
        }
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => View::class,
            'label_format' => 'view.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_FORM_DOMAIN,
            'create' => false,
            'ajax-save-url' => null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\DBAL\ReleaseStatusEnumType;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Release;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReleaseType extends AbstractType
{
    private $environmentService;

    public function __construct(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
    }

    /**
     * @param FormBuilderInterface<AbstractType> $builder
     * @param array<string, mixed>               $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $status = new ReleaseStatusEnumType();
        $builder
            ->add('name', null, [
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('execution_date', DateTimeType::class, [
                'required' => false,
                'date_widget' => 'single_text',
                'input' => 'datetime',
                'attr' => [
                    'class' => 'datetime-picker',
                    'data-date-format' => 'D/MM/YYYY HH:mm:ss',
                    'data-date-days-of-week-disabled' => '',
                    'data-date-disabled-hours' => '',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'required' => true,
                'choices' => $status->getValues(),
                'choice_label' => function (string $value) {
                    return $value;
                },
                'row_attr' => [
                    'class' => 'col-md-3',
                ],
            ])
            ->add('environments', ChoiceType::class, [
                'attr' => [
                    'class' => 'select2',
                ],
                'multiple' => true,
                'choices' => $this->environmentService->getAll(),
                'required' => true,
                'choice_label' => function (Environment $value) {
                    return '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName();
                },
                'choice_value' => function (Environment $value) {
                    if (null != $value) {
                        return $value->getId();
                    }

                    return $value;
                },
            ])
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ])
            ->add('saveAndClose', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Release::class,
            'label_format' => 'form.form.release.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
        ]);
    }
}

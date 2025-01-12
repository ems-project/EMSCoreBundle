<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\QuerySearch;
use EMS\CoreBundle\Form\DataTransformer\QuerySearchOptionsTransformer;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Subform\QuerySearchOptionsType;
use EMS\CoreBundle\Service\EnvironmentService;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
final class QuerySearchType extends AbstractType
{
    public function __construct(private readonly EnvironmentService $service)
    {
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('name', null, [
                'required' => true,
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
            ])
            ->add('environments', ChoiceType::class, [
                'attr' => [
                    'class' => 'select2',
                ],
                'multiple' => true,
                'choices' => $this->service->getEnvironments(),
                'required' => false,
                'row_attr' => [
                    'class' => 'col-md-6',
                ],
                'choice_label' => fn (Environment $value) => '<i class="fa fa-square text-'.$value->getColor().'"></i>&nbsp;&nbsp;'.$value->getName(),
                'choice_value' => function (Environment $value) {
                    if (null != $value) {
                        return $value->getId();
                    }

                    return $value;
                },
            ])
            ->add('options', QuerySearchOptionsType::class)
            ->add('save', SubmitEmsType::class, [
                'attr' => [
                    'class' => 'btn btn-primary btn-sm ',
                ],
                'icon' => 'fa fa-save',
            ]);
        $builder->get('options')->addModelTransformer(new QuerySearchOptionsTransformer());
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuerySearch::class,
            'label_format' => 'form.form.query_search.%name%',
            'translation_domain' => EMSCoreBundle::TRANS_DOMAIN,
            'constraints' => [
                new UniqueEntity(['fields' => ['name']]),
            ],
        ]);
    }
}

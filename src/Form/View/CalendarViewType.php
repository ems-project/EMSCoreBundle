<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class CalendarViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, LoggerInterface $logger, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Calendar: a view where you can schedule your object';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Calendar';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->add('dateRangeField', TextType::class, [
        ])->add('timeFormat', TextType::class, [
            'attr' => [
                'placeholder' => 'i.e. H(:mm)',
            ],
        ])->add('locale', TextType::class, [
            'attr' => [
                'placeholder' => 'i.e. fr',
            ],
        ])->add('firstDay', IntegerType::class, [
            'attr' => [
                'placeholder' => 'Sunday=0, Monday=1, Tuesday=2, etc.',
            ],
        ])->add('weekends', CheckboxType::class, [
        ])->add('slotDuration', TextType::class, [
            'attr' => [
                'placeholder' => 'i.e. 00:30:00',
            ],
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'calendar_view';
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        $search = new Search();
        $form = $formFactory->create(SearchFormType::class, $search, [
            'method' => 'GET',
            'light' => true,
        ]);

        $form->handleRequest($request);

        return [
            'view' => $view,
            'field' => $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['dateRangeField']),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'form' => $form->createView(),
        ];
    }
}

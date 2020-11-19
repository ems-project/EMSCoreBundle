<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\Form\CriteriaUpdateConfig;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\View\Criteria\CriteriaFilterType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig_Environment;

class CriteriaViewType extends ViewType
{
    /** @var Router */
    protected $router;

    public function __construct(FormFactory $formFactory, Twig_Environment $twig, Client $client, LoggerInterface $logger, Router $router)
    {
        parent::__construct($formFactory, $twig, $client, $logger);
        $this->router = $router;
    }

    public function getLabel(): string
    {
        return 'Criteria: a view where we can massively content types having critetira';
    }

    public function getName(): string
    {
        return 'Criteria';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('criteriaMode', ChoiceType::class, [
                'label' => 'Criteria mode',
                'choices' => [
                    'The records of this content type are the criterion of another content type' => 'another',
                    'The criterion of this content type are specifed in a internal collection' => 'internal',
                ],
                'expanded' => true,
            ])
            ->add('targetField', TextType::class, [
                'label' => 'The target field of the referenced content type (another content type mode)',
            ])
            ->add('categoryFieldPath', TextType::class, [
            ])
            ->add('criteriaField', TextType::class, [
                'label' => 'The collection field containing the list of criteria (internal collection mode)',
            ])->add('criteriaFieldPaths', TextareaType::class, [
                    'attr' => [
                        'rows' => 6,
                    ],
            ]);
    }

    public function getBlockPrefix(): string
    {
        return 'criteria_view';
    }

    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        $criteriaUpdateConfig = new CriteriaUpdateConfig($view, $this->logger);

        $form = $formFactory->create(CriteriaFilterType::class, $criteriaUpdateConfig, [
                'view' => $view,
                'action' => $this->router->generate('views.criteria.table', [
                    'view' => $view->getId(),
                ], UrlGeneratorInterface::RELATIVE_PATH),
        ]);

        $form->handleRequest($request);

        $categoryField = false;
        if ($view->getContentType()->getCategoryField()) {
            $categoryField = $view->getContentType()->getFieldType()->__get('ems_'.$view->getContentType()->getCategoryField());
        }

        return [
            'criteriaField' => $view->getOptions()['criteriaField'],
            'categoryField' => $categoryField,
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'criterionList' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['criteriaField']),
            'form' => $form->createView(),
        ];
    }
}

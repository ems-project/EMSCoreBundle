<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use EMS\CoreBundle\Entity\Form\CriteriaUpdateConfig;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\View\Criteria\CriteriaFilterType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class CriteriaViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, LoggerInterface $logger, protected RouterInterface $router, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Criteria: a view where we can massively edit content types having criteria';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Criteria';
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
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

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'criteria_view';
    }

    #[\Override]
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
            $categoryField = $view->getContentType()->getFieldType()->get('ems_'.$view->getContentType()->getCategoryField());
        }

        return [
            'criteriaField' => $view->getOptions()['criteriaField'],
            'categoryField' => $categoryField,
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'criterionList' => $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['criteriaField']),
            'form' => $form->createView(),
        ];
    }
}

<?php

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class GalleryViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, private readonly ElasticaService $elasticaService, LoggerInterface $logger, private readonly SearchService $searchService, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Gallery: a view where you can browse images';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Gallery';
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed>                       $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        $builder->add('imageField', TextType::class, [
        ])->add('sourceFields', TextType::class, [
            'required' => false,
        ])->add('imageAltFields', TextType::class, [
            'required' => false,
        ])->add('missingImageHash', TextType::class, [
            'required' => false,
        ])->add('thumbnailAssetConfigIdentifier', TextType::class, [
            'required' => false,
        ])->add('imageAssetConfigIdentifier', TextType::class, [
            'required' => false,
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'gallery_view';
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        $search = new Search();
        if (!$request->query->has('search_form')) {
            $search->getFirstFilter()->setField($view->getOptions()['imageField'].'.sha1');
            $search->getFirstFilter()->setBooleanClause('must');
        }
        $search->setContentTypes([$view->getContentType()->getName()]);
        $environment = $view->getContentType()->getEnvironment();
        if (null === $environment) {
            throw new \RuntimeException('Unexpected environment type');
        }
        $search->setEnvironments([$environment->getName()]);

        $form = $formFactory->create(SearchFormType::class, $search, [
            'method' => 'GET',
            'light' => true,
        ]);

        $form->handleRequest($request);

        $search = $form->getData();

        $elasticaSearch = $this->searchService->generateSearch($search);
        $elasticaSearch->setFrom(0);
        $elasticaSearch->setSize(1000);

        $sourceFields = $view->getOptions()['sourceFields'] ?? null;
        if (\is_string($sourceFields) && \strlen($sourceFields) > 0) {
            $source = \preg_split('/,/', $sourceFields);
            if (\is_array($source)) {
                $elasticaSearch->setSources($source);
            }
        }
        $resultSet = $this->elasticaService->search($elasticaSearch);

        return [
            'view' => $view,
            'field' => $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['imageField']),
            'imageAssetConfigIdentifier' => $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['imageAssetConfigIdentifier']),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->getEnvironment(),
            'form' => $form->createView(),
            'data' => $resultSet->getResponse()->getData(),
        ];
    }
}

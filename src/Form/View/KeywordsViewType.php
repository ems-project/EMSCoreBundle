<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\View;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\View;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class KeywordsViewType extends ViewType
{
    public function __construct(FormFactory $formFactory, Environment $twig, private readonly ElasticaService $elasticaService, LoggerInterface $logger, string $templateNamespace)
    {
        parent::__construct($formFactory, $twig, $logger, $templateNamespace);
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Keywords: a view where all properties of kind (such as keyword) are listed on a single page';
    }

    #[\Override]
    public function getName(): string
    {
        return 'Keywords';
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
        ->add('aggsQuery', TextareaType::class, [
            'label' => 'The aggregations Elasticsearch query [Twig]',
        ])
        ->add('template', TextareaType::class, [
            'label' => 'The Twig template used to display each keywords',
        ])
        ->add('pathToBuckets', TextType::class, [
            'label' => 'The twig path to the buckets array',
        ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'keywords_view';
    }

    #[\Override]
    public function getParameters(View $view, FormFactoryInterface $formFactory, Request $request): array
    {
        $searchQuery = [
            'index' => $view->getContentType()->giveEnvironment()->getAlias(),
            'type' => $view->getContentType()->getName(),
            'body' => $view->getOptions()['aggsQuery'],
        ];

        $search = $this->elasticaService->convertElasticsearchSearch($searchQuery);
        $resultSet = $this->elasticaService->search($search);

        $bucketPath = $view->getOptions()['pathToBuckets'] ?? null;
        $keywords = $resultSet->getResponse()->getData();
        if (!\is_array($keywords)) {
            throw new \RuntimeException('Unexpected response type');
        }
        if (\is_string($bucketPath)) {
            foreach (\explode('.', $bucketPath) as $attribute) {
                if (!isset($keywords[$attribute])) {
                    $keywords = [];
                    $this->logger->warning('log.view.keywords.warning.bucket_not_found', ['bucketPath' => $bucketPath]);
                    break;
                }
                $keywords = $keywords[$attribute];
            }
        }

        return [
            'keywords' => $keywords,
            'view' => $view,
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->giveEnvironment(),
        ];
    }
}

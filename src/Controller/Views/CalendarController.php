<?php

namespace EMS\CoreBundle\Controller\Views;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends AbstractController
{
    private DataService $dataService;
    private LoggerInterface $logger;
    private SearchService $searchService;
    private ElasticaService $elasticaService;

    public function __construct(LoggerInterface $logger, ElasticaService $elasticaService, DataService $dataService, SearchService $searchService)
    {
        $this->logger = $logger;
        $this->elasticaService = $elasticaService;
        $this->dataService = $dataService;
        $this->searchService = $searchService;
    }

    public function update(View $view, Request $request): Response
    {
        try {
            $ouuid = Type::string($request->request->get('ouuid'));
            $type = $view->getContentType()->getName();
            $revision = $this->dataService->initNewDraft($type, $ouuid);

            $rawData = $revision->getRawData();
            $field = $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['dateRangeField']);

            $from = new \DateTime(Type::string($request->request->get('start')));
            $to = $request->request->get('end', false);
            if (!$to) {
                $to = clone $from;
                $to->add(new \DateInterval('PT23H59M'));
            } else {
                $to = new \DateTime(Type::string($to));
            }

            $input = [
                $field->getMappingOptions()['fromDateMachineName'] => $from->format('c'),
                $field->getMappingOptions()['toDateMachineName'] => $to->format('c'),
            ];

            if ($field->getMappingOptions()['nested']) {
                $rawData[$field->getName()] = $input;
            } else {
                $rawData = \array_merge($rawData, $input);
            }

            $revision->setRawData($rawData);
            $this->dataService->finalizeDraft($revision);

            return $this->render('@EMSCore/view/custom/calendar_replan.json.twig', [
                'success' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
        }
    }

    public function searchAction(View $view, Request $request): Response
    {
        $search = new Search();
        $form = $this->createForm(SearchFormType::class, $search, [
            'method' => 'GET',
            'light' => true,
        ]);
        $form->handleRequest($request);

        $search = $form->getData();
        /* @var Search $search */
        $search->setEnvironments([$view->getContentType()->getName()]);

        $body = $this->searchService->generateSearchBody($search);

        $from = new \DateTime(Type::string($request->query->get('from')));
        $to = new \DateTime(Type::string($request->query->get('to')));
        $field = $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['dateRangeField']);

        if (empty($body['query']['bool']['must'])) {
            $body['query']['bool']['must'] = [];
        }
        if ($field->getMappingOptions()['nested']) {
            $body['query']['bool']['must'][] = [
                'nested' => [
                    'path' => $field->getName(),
                    'query' => [
                        'range' => [
                            $field->getName().'.'.$field->getMappingOptions()['fromDateMachineName'] => ['lte' => $to->format('c')],
                        ],
                    ],
                ],
            ];
            $body['query']['bool']['must'][] = [
                'nested' => [
                    'path' => $field->getName(),
                    'query' => [
                        'range' => [
                            $field->getName().'.'.$field->getMappingOptions()['toDateMachineName'] => ['gte' => $from->format('c')],
                        ],
                    ],
                ],
            ];
        } else {
            $body['query']['bool']['must'][] = [
                'range' => [
                    $field->getMappingOptions()['fromDateMachineName'] => ['lte' => $to->format('c')],
                ],
            ];
            $body['query']['bool']['must'][] = [
                'range' => [
                    $field->getMappingOptions()['toDateMachineName'] => ['gte' => $from->format('c')],
                ],
            ];
        }

        $searchQuery = [
            'index' => $view->getContentType()->giveEnvironment()->getAlias(),
            'type' => $view->getContentType()->getName(),
            'from' => 0,
            'size' => 1000,
            'body' => $body,
        ];

        $search = $this->elasticaService->convertElasticsearchSearch($searchQuery);

        return $this->render('@EMSCore/view/custom/calendar_search.json.twig', [
            'success' => true,
            'data' => $this->elasticaService->search($search)->getResponse()->getData(),
            'field' => $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['dateRangeField']),
            'contentType' => $view->getContentType(),
            'environment' => $view->getContentType()->giveEnvironment(),
        ]);
    }
}

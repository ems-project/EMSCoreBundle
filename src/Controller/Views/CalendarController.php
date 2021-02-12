<?php

namespace EMS\CoreBundle\Controller\Views;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\SearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CalendarController extends AppController
{
    /**
     * @return Response
     *
     * @Route("/views/calendar/replan/{view}.json", name="views.calendar.replan", defaults={"_format"="json"}, methods={"POST"})
     */
    public function updateAction(View $view, Request $request, DataService $dataService)
    {
        try {
            $ouuid = $request->request->get('ouuid', false);
            $type = $view->getContentType()->getName();
            $revision = $dataService->initNewDraft($type, $ouuid);

            $rawData = $revision->getRawData();
            /** @var FieldType $field */
            $field = $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']);

            /** @var \DateTime $from */
            $from = new \DateTime($request->request->get('start', false));
            $to = $request->request->get('end', false);
            if (!$to) {
                $to = clone $from;
                $to->add(new \DateInterval('PT23H59M'));
            } else {
                $to = new \DateTime($to);
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
            $dataService->finalizeDraft($revision);

            return $this->render('@EMSCore/view/custom/calendar_replan.json.twig', [
                    'success' => true,
            ]);
        } catch (\Exception $e) {
            $this->getLogger()->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);

            return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
        }
    }

    /**
     * @return Response
     *
     * @throws \Exception
     *
     * @Route("/views/calendar/search/{view}.json", name="views.calendar.search", defaults={"_format"="json"}, methods={"GET"})
     */
    public function searchAction(View $view, Request $request, ElasticaService $elasticaService, SearchService $searchService)
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

        $body = $searchService->generateSearchBody($search);

        /** @var \DateTime $from */
        $from = new \DateTime($request->query->get('from'));
        /** @var \DateTime $to */
        $to = new \DateTime($request->query->get('to'));
        /** @var FieldType $field */
        $field = $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']);

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

        /** @var Environment $viewEnvironment */
        $viewEnvironment = $view->getContentType()->getEnvironment();
        $searchQuery = [
                'index' => $viewEnvironment->getAlias(),
                'type' => $view->getContentType()->getName(),
                'from' => 0,
                'size' => 1000,
                'body' => $body,
        ];

        $search = $elasticaService->convertElasticsearchSearch($searchQuery);

        return $this->render('@EMSCore/view/custom/calendar_search.json.twig', [
                'success' => true,
                'data' => $elasticaService->search($search)->getResponse()->getData(),
                'field' => $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['dateRangeField']),
                'contentType' => $view->getContentType(),
                'environment' => $view->getContentType()->getEnvironment(),
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Controller\Views;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\UI\FlashMessageLogger;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Color;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ElasticaService $elasticaService,
        private readonly DataService $dataService,
        private readonly SearchService $searchService,
        private readonly FlashMessageLogger $flashMessageLogger,
    ) {
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

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);

            return $this->flashMessageLogger->buildJsonResponse([
                'success' => false,
            ]);
        }
    }

    public function search(View $view, Request $request): Response
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

        $field = $view->getContentType()->getFieldType()->get('ems_'.$view->getOptions()['dateRangeField']);
        $contentType = $view->getContentType();
        $environment = $view->getContentType()->giveEnvironment();
        $events = [];
        foreach ($this->elasticaService->search($search)->getResponse()->getData()['hits']['hits'] ?? [] as $item) {
            $source = $item['_source'];
            if ($field->getMappingOption('nested', true)) {
                $source = $source[$field->getName()] ?? [];
            }
            $event = [
                'id' => $item['id'] ?? null,
                'title' => $contentType->hasLabelField() && isset($item['_source'][$contentType->giveLabelField()]) ? $item['_source'][$contentType->giveLabelField()] : $item['id'] ?? null,
                'url' => $this->generateUrl('data.revisions', [
                    'type' => $contentType->getName(),
                    'ouuid' => $item['id'] ?? 'not-found',
                ]),
                'start' => $source[$field->getMappingOption('fromDateMachineName', 'not-found')] ?? null,
                'end' => $source[$field->getMappingOption('toDateMachineName', 'not-found')] ?? null,
                'allDay' => !$field->getDisplayOption('timePicker', false),
            ];
            if ($contentType->hasColorField() && isset($item['_source'][$contentType->giveColorField()])) {
                $color = new Color((string) $item['_source'][$contentType->giveColorField()]);
                $black = new Color('#000000');
                $white = new Color('#ffffff');

                $event['backgroundColor'] = $color->getRGB();
                $event['borderColor'] = $color->getRGB();
                $event['textColor'] = $color->contrastRatio($black) > $color->contrastRatio($white) ? $black->getRGB() : $white->getRGB();
            }
            $events[] = $event;
        }

        return $this->flashMessageLogger->buildJsonResponse([
            'success' => true,
            'events' => $events,
        ]);
    }
}

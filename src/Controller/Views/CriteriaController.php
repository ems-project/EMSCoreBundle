<?php

namespace EMS\CoreBundle\Controller\Views;

use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Response\Response as EmsResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Form\CriteriaUpdateConfig;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\ContentTypeStructureException;
use EMS\CoreBundle\Exception\DataStateException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PerformanceException;
use EMS\CoreBundle\Form\DataField\ContainerFieldType;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;
use EMS\CoreBundle\Form\View\Criteria\CriteriaFilterType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CriteriaController extends AppController
{
    /**
     * @return Response
     *
     * @throws DataStateException
     * @throws Exception
     *
     * @Route("/views/criteria/align/{view}", name="views.criteria.align", methods={"POST"})
     */
    public function alignAction(View $view, Request $request, ElasticaService $elasticaService, DataService $dataService, ContentTypeService $contentTypeService, ObjectChoiceListFactory $objectChoiceListFactory)
    {
        $criteriaUpdateConfig = new CriteriaUpdateConfig($view, $this->getLogger());
        $form = $this->createForm(CriteriaFilterType::class, $criteriaUpdateConfig, [
                'view' => $view,
        ]);

        $form->handleRequest($request);
        /** @var CriteriaUpdateConfig $criteriaUpdateConfig */
        $criteriaUpdateConfig = $form->getData();

        $tables = $this->generateCriteriaTable($view, $criteriaUpdateConfig, $elasticaService, $contentTypeService, $objectChoiceListFactory);
        $params = \explode(':', $request->request->all()['alignOn']);

        $isRowAlign = ('row' == $params[0]);
        $key = $params[1].':'.$params[2];

        $filters = [];
        $criteriaField = $view->getOptions()['criteriaField'];
        foreach ($tables['criteriaChoiceLists'] as $name => $criteria) {
            if (1 == \count($criteria)) {
                $filters[$name] = \array_keys($criteria)[0];
            }
        }

        $itemToFinalize = [];

        foreach ($tables['table'] as $rowId => $row) {
            foreach ($row as $colId => $col) {
                $alignWith = $tables['table'][$isRowAlign ? $key : $rowId][$isRowAlign ? $colId : $key];
                if (!empty($col)) {
                    /** @var ObjectChoiceListItem $toremove */
                    foreach ($col as $toremove) {
                        $found = false;
                        /* @var ObjectChoiceListItem $item */
                        if (!empty($alignWith)) {
                            foreach ($alignWith as $item) {
                                if ($item->getValue() == $toremove->getValue()) {
                                    $found = true;
                                    break;
                                }
                            }
                        }
                        if (!$found) {
                            $filters[$criteriaUpdateConfig->getRowCriteria()] = $rowId;
                            $filters[$criteriaUpdateConfig->getColumnCriteria()] = $colId;

                            if ('internal' == $view->getOptions()['criteriaMode']) {
                                if (isset($itemToFinalize[$toremove->getValue()])) {
                                    $revision = $itemToFinalize[$toremove->getValue()];
                                } else {
                                    $structuredTarget = \explode(':', $toremove->getValue());
                                    $type = $structuredTarget[0];
                                    $ouuid = $structuredTarget[1];

                                    /** @var Revision $revision */
                                    $revision = $dataService->getNewestRevision($type, $ouuid);
                                }

                                if ($revision = $this->removeCriteria($filters, $revision, $criteriaField, $dataService)) {
                                    $itemToFinalize[$toremove->getValue()] = $revision;
                                }
                            } else {
                                $rawData = $filters;
                                $targetFieldName = null;
                                if ($view->getContentType()->getCategoryField() && null !== $criteriaUpdateConfig->getCategory()) {
                                    $rawData[$view->getContentType()->getCategoryField()] = $criteriaUpdateConfig->getCategory()->getRawData();
                                }
                                if (isset($view->getOptions()['targetField'])) {
                                    $pathTargetField = $view->getOptions()['targetField'];
                                    $pathTargetField = \explode('.', $pathTargetField);
                                    $targetFieldName = \array_pop($pathTargetField);
                                    $rawData[$targetFieldName] = $toremove->getValue();
                                }

                                $revision = $this->removeCriteriaRevision($elasticaService, $view, $rawData, $targetFieldName, $dataService, $itemToFinalize);
//                                 $revision = $this->addCriteriaRevision($view, $rawData, $targetFieldName, $itemToFinalize);
                                if ($revision) {
                                    $itemToFinalize[$revision->getOuuid()] = $revision;
                                }
                            }
                        }
                    }
                }
                if (!empty($alignWith)) {
                    /** @var ObjectChoiceListItem $toadd */
                    foreach ($alignWith as $toadd) {
                        $found = false;
                        /* @var ObjectChoiceListItem $item */
                        if (!empty($col)) {
                            foreach ($col as $item) {
                                if ($item->getValue() == $toadd->getValue()) {
                                    $found = true;
                                    break;
                                }
                            }
                        }
                        if (!$found) {
                            $filters[$criteriaUpdateConfig->getRowCriteria()] = $rowId;
                            $filters[$criteriaUpdateConfig->getColumnCriteria()] = $colId;

                            if ('internal' == $view->getOptions()['criteriaMode']) {
                                if (isset($itemToFinalize[$toadd->getValue()])) {
                                    $revision = $itemToFinalize[$toadd->getValue()];
                                } else {
                                    $structuredTarget = \explode(':', $toadd->getValue());
                                    $type = $structuredTarget[0];
                                    $ouuid = $structuredTarget[1];

                                    /** @var Revision $revision */
                                    $revision = $dataService->getNewestRevision($type, $ouuid);
                                }

                                if ($revision = $this->addCriteria($filters, $revision, $criteriaField, $dataService)) {
                                    $itemToFinalize[$toadd->getValue()] = $revision;
                                }
                            } else {
                                $rawData = $filters;
                                $targetFieldName = null;
                                if ($view->getContentType()->getCategoryField() && null !== $criteriaUpdateConfig->getCategory()) {
                                    $rawData[$view->getContentType()->getCategoryField()] = $criteriaUpdateConfig->getCategory()->getRawData();
                                }
                                if (isset($view->getOptions()['targetField'])) {
                                    $pathTargetField = $view->getOptions()['targetField'];
                                    $pathTargetField = \explode('.', $pathTargetField);
                                    $targetFieldName = \array_pop($pathTargetField);
                                    $rawData[$targetFieldName] = $toadd->getValue();
                                }

                                $revision = $this->addCriteriaRevision($elasticaService, $view, $rawData, $targetFieldName, $dataService, $itemToFinalize);
                                if ($revision) {
                                    $itemToFinalize[$revision->getOuuid()] = $revision;
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($itemToFinalize as $revision) {
            $dataService->finalizeDraft($revision);
        }
        \sleep(2);
        $elasticaService->getClusterHealth('green', '20s');

        return $this->forward('EMSCoreBundle:Views\Criteria:generateCriteriaTable', ['view' => $view]);
    }

    private function isAuthorized(FieldType $criteriaField, AuthorizationCheckerInterface $security)
    {
        $authorized = empty($criteriaField->getMinimumRole()) || $security->isGranted($criteriaField->getMinimumRole());
        if ($authorized) {
            foreach ($criteriaField->getChildren() as $child) {
                $authorized = $this->isAuthorized($child, $security);
                if (!$authorized) {
                    break;
                }
            }
        }

        return $authorized;
    }

    /**
     * @return Response
     *
     * @throws Exception
     *
     * @Route("/views/criteria/table/{view}", name="views.criteria.table", methods={"GET", "POST"})
     */
    public function generateCriteriaTableAction(View $view, Request $request, ElasticaService $elasticaService, AuthorizationCheckerInterface $authorizationChecker, ContentTypeService $contentTypeService, ObjectChoiceListFactory $objectChoiceListFactory, LoggerInterface $logger)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var RevisionRepository $revisionRep */
        $revisionRep = $em->getRepository('EMSCoreBundle:Revision');
        $counters = $revisionRep->draftCounterGroupedByContentType([], true);

        foreach ($counters as $counter) {
            if ($counter['content_type_id'] == $view->getContentType()->getId()) {
                $this->getLogger()->warning('log.view.criteria.draft_in_progress', [
                    'count' => $counter['counter'],
                ]);
            }
        }

        $criteriaUpdateConfig = new CriteriaUpdateConfig($view, $logger);

        $form = $this->createForm(CriteriaFilterType::class, $criteriaUpdateConfig, [
                'view' => $view,
                'attr' => [
                    'id' => 'criteria_filter',
                        'action' => $this->generateUrl('data.customindexview', ['viewId' => $view->getId()], UrlGeneratorInterface::RELATIVE_PATH),
                ],
        ]);

        $form->handleRequest($request);
        /** @var CriteriaUpdateConfig $criteriaUpdateConfig */
        $criteriaUpdateConfig = $form->getData();

        $contentType = $view->getContentType();
        $valid = true;
        if (!empty($view->getOptions()['categoryFieldPath']) && empty($criteriaUpdateConfig->getCategory()->getTextValue())) {
            $valid = false;
            $form->get('category')->addError(new FormError('Category is mandatory'));
        }

        foreach ($form->get('criterion')->all() as $child) {
            if ($child->getConfig()->getName() != $criteriaUpdateConfig->getColumnCriteria()
                    && $child->getConfig()->getName() != $criteriaUpdateConfig->getRowCriteria()
                    && empty($child->getNormData()->getTextValue())) {
                $valid = false;
                $child->addError(new FormError('Non-row/column criteria are mandatory'));
            }
        }

        if (!$valid) {
            return $this->render('@EMSCore/view/custom/criteria_view.html.twig', [
                    'view' => $view,
                    'form' => $form->createView(),
                    'contentType' => $contentType,
            ]);
        }

        $criteriaField = $view->getContentType()->getFieldType();
        if ('internal' == $view->getOptions()['criteriaMode']) {
            $criteriaField = $view->getContentType()->getFieldType()->__get('ems_'.$view->getOptions()['criteriaField']);
        } elseif ('another' == $view->getOptions()['criteriaMode']) {
        } else {
            throw new Exception('Should never happen');
        }

        $columnField = null;
        $rowField = null;
        $fieldPaths = \preg_split('/\\r\\n|\\r|\\n/', $view->getOptions()['criteriaFieldPaths']);

        $authorized = $this->isAuthorized($criteriaField, $authorizationChecker) && $authorizationChecker->isGranted($view->getContentType()->getEditRole());

        foreach ($fieldPaths as $path) {
            /** @var FieldType $child */
            $child = $criteriaField->getChildByPath($path);
            if ($child) {
                if ($child->getName() == $criteriaUpdateConfig->getColumnCriteria()) {
                    $columnField = $child;
                } elseif ($child->getName() == $criteriaUpdateConfig->getRowCriteria()) {
                    $rowField = $child;
                }

                $authorized = $authorized && $this->isAuthorized($child, $authorizationChecker);
            }
        }
        if (!$authorized) {
            $this->getLogger()->notice('log.view.criteria.update_not_authorized', [
            ]);
        }

        $tables = $this->generateCriteriaTable($view, $criteriaUpdateConfig, $elasticaService, $contentTypeService, $objectChoiceListFactory);

        return $this->render('@EMSCore/view/custom/criteria_table.html.twig', [
            'table' => $tables['table'],
            'rowFieldType' => $rowField,
            'columnFieldType' => $columnField,
            'config' => $criteriaUpdateConfig,
            'columns' => $tables['criteriaChoiceLists'][$criteriaUpdateConfig->getColumnCriteria()],
            'rows' => $tables['criteriaChoiceLists'][$criteriaUpdateConfig->getRowCriteria()],
            'criteriaChoiceLists' => $tables['criteriaChoiceLists'],
            'view' => $view,
            'categoryChoiceList' => $tables['categoryChoiceList'],
            'targetContentType' => $tables['targetContentType'],
            'authorized' => $authorized,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return array
     *
     * @throws ContentTypeStructureException
     * @throws ElasticmsException
     * @throws PerformanceException
     * @throws Exception
     */
    public function generateCriteriaTable(View $view, CriteriaUpdateConfig $criteriaUpdateConfig, ElasticaService $elasticaService, ContentTypeService $contentTypeService, ObjectChoiceListFactory $objectChoiceListFactory)
    {
        $contentType = $view->getContentType();

//        $criteriaField = $contentType->getFieldType();

        $criteriaFieldName = false;
        if ('internal' == $view->getOptions()['criteriaMode']) {
            $criteriaFieldName = $view->getOptions()['criteriaField'];
//            $criteriaField = $contentType->getFieldType()->getChildByPath($criteriaFieldName);
        }

        $body = [
                'query' => [
                        'bool' => [
                                'must' => [
                                ],
                        ],
                ],
        ];

        $categoryChoiceList = false;
        if (null !== $criteriaUpdateConfig->getCategory()) {
            $dataField = $criteriaUpdateConfig->getCategory();
            if ($dataField->getRawData() && \strlen($dataField->getTextValue()) > 0) {
                $categoryFieldTypeName = $dataField->getFieldType()->getType();
                /** @var DataFieldType $categoryFieldType */
                $categoryFieldType = $this->getDataFieldType($categoryFieldTypeName);

                $body['query']['bool']['must'][] = $categoryFieldType->getElasticsearchQuery($dataField);
                $categoryChoiceList = $categoryFieldType->getChoiceList($dataField->getFieldType(), [$dataField->getRawData()]);
            }
        }

        $criteriaFilters = [];
        $criteriaChoiceLists = [];
        /** @var DataField $criteria */
        foreach ($criteriaUpdateConfig->getCriterion() as $criteria) {
            $fieldTypeName = $criteria->getFieldType()->getType();
            /** @var DataFieldType $dataFieldType */
            $dataFieldType = $this->getDataFieldType($fieldTypeName);
            if (\count($criteria->getRawData()) > 0) {
                if ($criteriaFieldName) {
                    $criteriaFilters[] = $dataFieldType->getElasticsearchQuery($criteria, ['nested' => $criteriaFieldName]);
                } else {
                    $body['query']['bool']['must'][] = $dataFieldType->getElasticsearchQuery($criteria, []);
                }
            }
            $choicesList = $dataFieldType->getChoiceList($criteria->getFieldType(), $criteria->getRawData());
            $criteriaChoiceLists[$criteria->getFieldType()->getName()] = $choicesList;
        }

        if ($criteriaFieldName) {
            $body['query']['bool']['must'][] = [
                'nested' => [
                    'path' => $criteriaFieldName,
                    'query' => [
                            'bool' => ['must' => $criteriaFilters],
                    ],
                ],
            ];
        }
//        /** @var FieldType $columnField */
//        $columnField = $criteriaField->getChildByPath($criteriaUpdateConfig->getColumnCriteria());
//
//
//        /** @var FieldType $rowField */
//        $rowField = $criteriaField->getChildByPath($criteriaUpdateConfig->getRowCriteria());

        $table = [];
        /** @var ObjectChoiceListItem $rowItem */
        foreach ($criteriaChoiceLists[$criteriaUpdateConfig->getRowCriteria()] as $rowItem) {
            $table[$rowItem->getValue()] = [];
            /** @var ObjectChoiceListItem $columnItem */
            foreach ($criteriaChoiceLists[$criteriaUpdateConfig->getColumnCriteria()] as $columnItem) {
                $table[$rowItem->getValue()][$columnItem->getValue()] = null;
            }
        }

        $search = $elasticaService->convertElasticsearchSearch([
            'index' => $contentType->giveEnvironment()->getAlias(),
            'type' => $contentType->getName(),
            'body' => $body,
            'size' => 500, //is it enough?
        ]);
        $response = EmsResponse::fromResultSet($elasticaService->search($search));

        if ($response->getTotal() > $response->getTotalDocuments()) {
            $this->getLogger()->error('log.view.criteria.too_many_criteria', [
                'total' => $response->getTotal(),
            ]);
        }

        $targetField = false;
        $loaderTypes = $view->getContentType()->getName();
        $targetContentType = null;
        if ($view->getOptions()['targetField']) {
            $targetField = $contentType->getFieldType()->getChildByPath($view->getOptions()['targetField']);
            if ($targetField && isset($targetField->getOptions()['displayOptions']['type'])) {
                $loaderTypes = $targetField->getOptions()['displayOptions']['type'];
                $targetContentType = $contentTypeService->getByName($loaderTypes);
            }
        }

        $loader = $objectChoiceListFactory->createLoader($loaderTypes, false);

        /** @var Document $document */
        foreach ($response->getDocuments() as $document) {
            $value = $document->getEmsId();
            if ($targetField) {
                $value = $document->getSource()[$targetField->getName()];
            }

            $choices = $loader->loadChoiceList()->loadChoices([$value]);

            if (isset($choices[$value])) {
                $choice = $choices[$value];
                $source = $document->getSource();
                if ('internal' == $view->getOptions()['criteriaMode']) {
                    foreach ($source[$criteriaFieldName] as $criterion) {
                        $this->addToTable($choice, $table, $criterion, \array_keys($criteriaChoiceLists), $criteriaChoiceLists, $criteriaUpdateConfig);
                    }
                } elseif ('another' == $view->getOptions()['criteriaMode']) {
                    $this->addToTable($choice, $table, $source, \array_keys($criteriaChoiceLists), $criteriaChoiceLists, $criteriaUpdateConfig);
                } else {
                    throw new Exception('Should never happen');
                }
            } else {
                $this->getLogger()->warning('log.view.criteria.document_key_not_found', [
                    'document_reference' => $value,
                    EmsFields::LOG_CONTENTTYPE_FIELD => $document->getContentType(),
                    EmsFields::LOG_OUUID_FIELD => $document->getId(),
                ]);
            }
        }

        return [
            'table' => $table,
            'criteriaChoiceLists' => $criteriaChoiceLists,
            'categoryChoiceList' => $categoryChoiceList,
            'targetContentType' => $targetContentType,
        ];
    }

    /**
     * @return Response
     *
     * @throws DataStateException
     * @throws Exception
     *
     * @Route("/views/criteria/addCriterion/{view}", name="views.criteria.add", methods={"POST"})
     */
    public function addCriteriaAction(View $view, Request $request, ElasticaService $elasticaService, AuthorizationCheckerInterface $authorizationChecker, DataService $dataService)
    {
        $filters = $request->request->get('filters');
        $target = $request->request->get('target');
        $criteriaField = $request->request->get('criteriaField');
        $category = $request->request->get('category');

        //TODO securtity test

        if ('internal' == $view->getOptions()['criteriaMode']) {
            $structuredTarget = \explode(':', $target);

            $type = $structuredTarget[0];
            $ouuid = $structuredTarget[1];

            $revision = $dataService->getNewestRevision($type, $ouuid);
            if (!$revision instanceof Revision) {
                throw new \RuntimeException('Unexpected revision type');
            }

            $authorized = $authorizationChecker->isGranted($view->getContentType()->getEditRole());
            if (!$authorized) {
                $this->getLogger()->warning('log.view.criteria.update_privilege_issue', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }

            if ($revision->getDraft()) {
                $this->getLogger()->warning('log.view.criteria.draft_in_progress', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => 0,
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }

            try {
                if ($revision = $this->addCriteria($filters, $revision, $criteriaField, $dataService)) {
                    $dataService->finalizeDraft($revision);
                }
            } catch (LockedException $e) {
                if (!$revision instanceof Revision) {
                    throw new \RuntimeException('Unexpected revision type');
                }

                $this->getLogger()->warning('log.view.criteria.locked_revision', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => 0,
                    'locked_by' => $revision->getLockBy(),
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }
        } else {
            $rawData = $filters;
            $targetFieldName = null;
            if ($view->getContentType()->getCategoryField() && $category) {
                $rawData[$view->getContentType()->getCategoryField()] = $category;
            }
            if (isset($view->getOptions()['targetField'])) {
                $pathTargetField = $view->getOptions()['targetField'];
                $pathTargetField = \explode('.', $pathTargetField);
                $targetFieldName = \array_pop($pathTargetField);
                $rawData[$targetFieldName] = $target;
            }
            $revision = $this->addCriteriaRevision($elasticaService, $view, $rawData, $targetFieldName, $dataService);
            if ($revision) {
                $dataService->finalizeDraft($revision);
            }
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => true,
        ]);
    }

    /**
     * @param string|null $targetFieldName
     *
     * @return bool|Revision|mixed|null
     *
     * @throws DataStateException
     */
    public function addCriteriaRevision(ElasticaService $elasticaService, View $view, array $rawData, $targetFieldName, DataService $dataService, array $loadedRevision = [])
    {
        $multipleField = $this->getMultipleField($view->getContentType()->getFieldType());

        $boolQuery = $elasticaService->getBoolQuery();

        foreach ($rawData as $name => $key) {
            if ($multipleField !== $name) {
                $boolQuery->addMust($elasticaService->getTermsQuery($name, [$key]));
            }
        }
        $query = $elasticaService->filterByContentTypes($boolQuery, [$view->getContentType()->getName()]);

        $search = new Search([$view->getContentType()->giveEnvironment()->getAlias()], $query);
        $response = EmsResponse::fromResultSet($elasticaService->search($search));

        if (0 == $response->getTotal()) {
            $revision = false;
            foreach ($loadedRevision as $item) {
                $found = true;
                foreach ($rawData as $name => $key) {
                    if ($multipleField != $name) {
                        if ($item->getRawData()[$name] != $key) {
                            $found = false;
                            break;
                        }
                    }
                }
                if ($found) {
                    $revision = $item;
                }
            }

            $multipleValueToAdd = $rawData[$multipleField];
            if (!$revision) {
                $revision = new Revision();
                $revision->setStartTime(new \DateTime());
                $revision->setContentType($view->getContentType());
                $rawData[$multipleField] = [];
                $revision->setRawData($rawData);
            }
            $rawData = $revision->getRawData();
            $rawData[$multipleField][] = $multipleValueToAdd;
            $revision->setRawData($rawData);

            $message = $multipleValueToAdd;
            foreach ($rawData as $key => $value) {
                if ($key != $multipleField && $key != $targetFieldName) {
                    $message .= ', '.$value;
                }
            }

            $revision = $dataService->finalizeDraft($revision);

            $this->getLogger()->notice('log.view.criteria.new_criteria', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                'target_field_name' => $targetFieldName,
                'target_field_data' => $rawData[$targetFieldName],
                'message' => $message,
            ]);

            return $revision;
        } elseif (1 == $response->getTotal()) {
            /** @var Revision $revision */
            $revision = null;
            /** @var Document $document */
            foreach ($response->getDocuments() as $document) {
                if (isset($loadedRevision[$document->getId()])) {
                    $revision = $loadedRevision[$document->getId()];
                } else {
                    $revision = $dataService->initNewDraft($view->getContentType()->getName(), $document->getId());
                }
            }

            $multipleValueToAdd = $rawData[$multipleField];
            $rawData = $revision->getRawData();
            if (\in_array($multipleValueToAdd, $rawData[$multipleField] ?? [])) {
                $this->getLogger()->warning('log.view.criteria.already_exists', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'field_name' => $multipleField,
                    'field_data' => $multipleValueToAdd,
                ]);
            } else {
                $rawData[$multipleField][] = $multipleValueToAdd;
                $revision->setRawData($rawData);
                $message = $multipleValueToAdd;
                foreach ($rawData as $key => $value) {
                    if ($key != $multipleField && $key != $targetFieldName) {
                        $message .= ', '.$value;
                    }
                }
                $this->getLogger()->notice('log.view.criteria.added', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'field_name' => $targetFieldName,
                    'field_data' => $rawData[$targetFieldName],
                    'message' => $message,
                ]);
            }

            return $revision;
        } else {
            $message = false;
            /** @var Document $document */
            foreach ($response->getDocuments() as $document) {
                if ($message) {
                    $message .= ', ';
                } else {
                    $message = '';
                }
                $message .= $document->getId();
            }
            $this->getLogger()->error('log.view.criteria.too_may_criteria', [
                'total' => $response->getTotal(),
                'message' => $message,
            ]);
        }

        return null;
    }

    /**
     * @param array  $filters
     * @param string $criteriaField
     *
     * @return bool|Revision
     *
     * @throws Exception
     */
    public function addCriteria($filters, Revision $revision, $criteriaField, DataService $dataService)
    {
        $rawData = $revision->getRawData();
        if (!isset($rawData[$criteriaField])) {
            $rawData[$criteriaField] = [];
        }
        $multipleField = $this->getMultipleField($revision->giveContentType()->getFieldType()->__get('ems_'.$criteriaField));

        $found = false;
        foreach ($rawData[$criteriaField] as &$criteriaSet) {
            $found = true;
            foreach ($filters as $criterion => $value) {
                if ($criterion != $multipleField && $value != $criteriaSet[$criterion]) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                if ($multipleField && false === \array_search($filters[$multipleField], $criteriaSet[$multipleField])) {
                    $criteriaSet[$multipleField][] = $filters[$multipleField];
                    if (!$revision->getDraft()) {
                        $revision = $dataService->initNewDraft($revision->giveContentType()->getName(), $revision->getOuuid(), $revision);
                    }
                    $revision->setRawData($rawData);
                    $this->getLogger()->notice('log.view.criteria.added', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        'field_name' => $multipleField,
                        'field_data' => $filters[$multipleField],
                    ]);

                    return $revision;
                } else {
                    $this->getLogger()->notice('log.view.criteria.already_exists', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        'field_name' => $multipleField,
                        'field_data' => $filters[$multipleField],
                    ]);
                }
                break;
            }
        }

        if (!$found) {
            $newCriterion = [];
            foreach ($filters as $criterion => $value) {
                if ($criterion == $multipleField) {
                    $newCriterion[$criterion] = [$value];
                } else {
                    $newCriterion[$criterion] = $value;
                }
            }
            $rawData[$criteriaField][] = $newCriterion;
            if (!$revision->getDraft()) {
                $revision = $dataService->initNewDraft($revision->giveContentType()->getName(), $revision->getOuuid(), $revision);
            }
            $revision->setRawData($rawData);

            return $revision;
        }

        return false;
    }

    /**
     * @return Response
     *
     * @throws DataStateException
     * @throws Exception
     *
     * @Route("/views/criteria/removeCriterion/{view}", name="views.criteria.remove", methods={"POST"})
     */
    public function removeCriteriaAction(View $view, Request $request, ElasticaService $elasticaService, DataService $dataService)
    {
        $filters = $request->request->get('filters');
        $target = $request->request->get('target');
        $criteriaField = $request->request->get('criteriaField');
        $category = $request->request->get('category');

        //TODO securtity test

        if ('internal' == $view->getOptions()['criteriaMode']) {
            $structuredTarget = \explode(':', $target);

            $type = $structuredTarget[0];
            $ouuid = $structuredTarget[1];

            /** @var Revision $revision */
            $revision = $dataService->getNewestRevision($type, $ouuid);

            if ($revision->getDraft()) {
                $this->getLogger()->warning('log.view.criteria.draft_in_progress', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => 0,
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }

            try {
                if ($revision = $this->removeCriteria($filters, $revision, $criteriaField, $dataService)) {
                    $dataService->finalizeDraft($revision);
                }
            } catch (LockedException $e) {
                if (!$revision instanceof Revision) {
                    throw new \RuntimeException('Unexpected revision type');
                }

                $this->getLogger()->warning('log.view.criteria.locked_revision', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => 0,
                    'locked_by' => $revision->getLockBy(),
                ]);

                return $this->render('@EMSCore/ajax/notification.json.twig', [
                        'success' => false,
                ]);
            }
        } else {
            $rawData = $filters;
            $targetFieldName = null;
            if ($view->getContentType()->getCategoryField() && $category) {
                $rawData[$view->getContentType()->getCategoryField()] = $category;
            }
            if (isset($view->getOptions()['targetField'])) {
                $pathTargetField = $view->getOptions()['targetField'];
                $pathTargetField = \explode('.', $pathTargetField);
                $targetFieldName = \array_pop($pathTargetField);
                $rawData[$targetFieldName] = $target;
            }
            $revision = $this->removeCriteriaRevision($elasticaService, $view, $rawData, $targetFieldName, $dataService);
            if ($revision) {
                $dataService->finalizeDraft($revision);
            }
        }

        return $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => true,
        ]);
    }

    /**
     * @param ?string $targetFieldName
     *
     * @return Revision|mixed|null
     *
     * @throws Exception
     */
    public function removeCriteriaRevision(ElasticaService $elasticaService, View $view, array $rawData, $targetFieldName, DataService $dataService, array $loadedRevision = [])
    {
        $multipleField = $this->getMultipleField($view->getContentType()->getFieldType());

        $body = [
                'query' => [
                        'bool' => [
                                'must' => [
                                ],
                        ],
                ],
        ];

        foreach ($rawData as $name => $key) {
            $body['query']['bool']['must'][] = [
                    'term' => [
                            $name => [
                                    'value' => $key,
                            ],
                    ],
            ];
        }

        $search = $elasticaService->convertElasticsearchSearch([
                'body' => $body,
                'index' => $view->getContentType()->getEnvironment()->getAlias(),
                'type' => $view->getContentType()->getName(),
        ]);
        $response = EmsResponse::fromResultSet($elasticaService->search($search));

        if (0 == $response->getTotal()) {
            $this->getLogger()->warning('log.view.criteria.not_found', [
                'field_name' => $targetFieldName,
            ]);
        } elseif (1 == $response->getTotal()) {
            /** @var Revision $revision */
            $revision = null;
            $queryDocument = null;
            /** @var Document $document */
            foreach ($response->getDocuments() as $document) {
                $queryDocument = $document;
                if (isset($loadedRevision[$document->getId()])) {
                    $revision = $loadedRevision[$document->getId()];
                } else {
                    $revision = $dataService->getNewestRevision($view->getContentType()->getName(), $document->getId());
                }
            }
            if (null === $queryDocument) {
                throw new \RuntimeException('Unexpected null document');
            }

            $multipleValueToRemove = $rawData[$multipleField];
            $rawData = $revision->getRawData();
            if (($key = \array_search($multipleValueToRemove, $rawData[$multipleField])) !== false) {
                $revision = $dataService->initNewDraft($view->getContentType()->getName(), $queryDocument->getId());
                unset($rawData[$multipleField][$key]);
                $rawData[$multipleField] = \array_values($rawData[$multipleField]);
                $revision->setRawData($rawData);
                $message = $multipleValueToRemove;
                foreach ($rawData as $key => $value) {
                    if ($key != $multipleField && $key != $targetFieldName) {
                        $message .= ', '.$value;
                    }
                }
                $this->getLogger()->info('log.view.criteria.removed', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'field_name' => $targetFieldName,
                    'field_data' => $rawData[$targetFieldName],
                ]);

                return $revision;
            } else {
                $this->getLogger()->warning('log.view.criteria.already_missing', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'field_name' => $targetFieldName,
                    'field_data' => $rawData[$targetFieldName],
                ]);
            }
        } else {
            $message = false;
            /** @var Document $document */
            foreach ($response->getDocuments() as $document) {
                if ($message) {
                    $message .= ', ';
                } else {
                    $message = '';
                }
                $message .= $document->getId();
            }
            $this->getLogger()->notice('log.view.criteria.too_many_criteria', [
                'total' => $response->getTotal(),
                'message' => $message,
            ]);
        }

        return null;
    }

    /**
     * @param array  $filters
     * @param string $criteriaField
     *
     * @return bool|Revision
     *
     * @throws Exception
     */
    public function removeCriteria($filters, Revision $revision, $criteriaField, DataService $dataService)
    {
        $rawData = $revision->getRawData();
        if (!isset($rawData[$criteriaField])) {
            $rawData[$criteriaField] = [];
        }
        $criteriaFieldType = $revision->giveContentType()->getFieldType()->__get('ems_'.$criteriaField);
        $multipleField = $this->getMultipleField($criteriaFieldType);

        $found = false;
        foreach ($rawData[$criteriaField] as $index => $criteriaSet) {
            $found = true;
            foreach ($filters as $criterion => $value) {
                if ($criterion != $multipleField && $value != $criteriaSet[$criterion]) {
                    $found = false;
                    break;
                }
            }
            if ($found) {
                if ($multipleField) {
                    $indexKey = \array_search($filters[$multipleField], $criteriaSet[$multipleField]);
                    if (false === $indexKey) {
                        $this->getLogger()->notice('log.view.criteria.not_found', [
                            'field_name' => $multipleField,
                        ]);
                    } else {
                        unset($rawData[$criteriaField][$index][$multipleField][$indexKey]);
                        $rawData[$criteriaField][$index][$multipleField] = \array_values($rawData[$criteriaField][$index][$multipleField]);
                        if (0 == \count($rawData[$criteriaField][$index][$multipleField])) {
                            unset($rawData[$criteriaField][$index]);
                            $rawData[$criteriaField] = \array_values($rawData[$criteriaField]);
                        }

                        if (!$revision->getDraft()) {
                            $revision = $dataService->initNewDraft($revision->giveContentType()->getName(), $revision->getOuuid(), $revision);
                        }
                        $revision->setRawData($rawData);
                        $this->getLogger()->notice('log.view.criteria.removed', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            'field_name' => $multipleField,
                            'field_data' => $filters[$multipleField],
                        ]);

                        return $revision;
                    }
                } else {
                    unset($rawData[$criteriaField][$index]);
                    $rawData[$criteriaField][$index] = \array_values($rawData[$criteriaField][$index]);

                    if (!$revision->getDraft()) {
                        $revision = $dataService->initNewDraft($revision->giveContentType()->getName(), $revision->getOuuid(), $revision);
                    }
                    $revision->setRawData($rawData);
                    $this->getLogger()->notice('log.view.criteria.removed', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        'field_name' => $multipleField,
                    ]);

                    return $revision;
                }
                break;
            }
        }

        if (!$found) {
            $this->getLogger()->notice('log.view.criteria.document_key_not_found', [
                'document_reference' => $criteriaField,
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            ]);
        }

        return false;
    }

    private function addToTable(ObjectChoiceListItem &$choice, array &$table, array &$criterion, array $criteriaNames, array &$criteriaChoiceLists, CriteriaUpdateConfig &$config, array $context = [])
    {
        $criteriaName = \array_pop($criteriaNames);
        $criterionList = $criterion[$criteriaName];
        if (!\is_array($criterionList)) {
            $criterionList = [$criterionList];
        }
        foreach ($criterionList as $value) {
            if (isset($criteriaChoiceLists[$criteriaName][$value])) {
                $context[$criteriaName] = $value;
                if (\count($criteriaNames) > 0) {
                    //let see (recursively) if the other criterion applies to find a matching context
                    $this->addToTable($choice, $table, $criterion, $criteriaNames, $criteriaChoiceLists, $config, $context);
                } else {
                    //all criterion apply the current choice can be added to the table depending the context
                    if (!isset($table[$context[$config->getRowCriteria()]][$context[$config->getColumnCriteria()]])) {
                        $table[$context[$config->getRowCriteria()]][$context[$config->getColumnCriteria()]] = [];
                    }
                    $table[$context[$config->getRowCriteria()]][$context[$config->getColumnCriteria()]][] = $choice;
                }
            }
        }
    }

    private function getMultipleField(FieldType $criteriaFieldType)
    {
        /** @var FieldType $criteria */
        foreach ($criteriaFieldType->getChildren() as $criteria) {
            if (!$criteria->getDeleted()) {
                if (ContainerFieldType::class == $criteria->getType()) {
                    $out = $this->getMultipleField($criteria);
                    if ($out) {
                        return $out;
                    }
                } elseif (isset($criteria->getDisplayOptions()['multiple']) && $criteria->getDisplayOptions()['multiple']) {
                    return $criteria->getName();
                }
            }
        }

        return false;
    }

    /**
     * @return Response
     *
     * @Route("/views/criteria/fieldFilter", name="views.criteria.fieldFilter")
     */
    public function fieldFilterAction(Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:FieldType');

        /** @var FieldType $field */
        $field = $repository->find($request->query->get('targetField'));

        $choices = $field->getDisplayOptions()['choices'];
        $choices = \explode("\n", \str_replace("\r", '', $choices));
        $labels = $field->getDisplayOptions()['labels'];
        $labels = \explode("\n", \str_replace("\r", '', $labels));

        $out = [
            'incomplete_results' => false,
            'total_count' => \count($choices),
            'items' => [],
        ];

        foreach ($choices as $idx => $choice) {
            $label = isset($labels[$idx]) ? $labels[$idx] : $choice;
            if (!$request->query->get('q') || \stristr($choice, $request->query->get('q')) || \stristr($label, $request->query->get('q'))) {
                $out['items'][] = [
                        'id' => $choice,
                        'text' => $label,
                ];
            }
        }

        return new Response(\json_encode($out));
    }
}

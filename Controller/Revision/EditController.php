<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\Revision\LoggingContext;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class EditController extends AbstractController
{
    /** @var DataService */
    private $dataService;
    /** @var LoggerInterface */
    private $logger;
    /** @var PublishService */
    private $publishService;
    /** @var TranslatorInterface */
    private $translator;
    /** @var WysiwygStylesSetService */
    private $wysiwygStylesSetService;

    public function __construct(
        DataService $dataService,
        LoggerInterface $logger,
        PublishService $publishService,
        TranslatorInterface $translator,
        WysiwygStylesSetService $wysiwygStylesSetService
    ) {
        $this->dataService = $dataService;
        $this->logger = $logger;
        $this->publishService = $publishService;
        $this->translator = $translator;
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
    }

    /**
     * @Route("/data/draft/edit/{revisionId}", name="ems_revision_edit"))
     * @Route("/data/draft/edit/{revisionId}", name="revision.edit"))
     */
    public function editRevision(int $revisionId, Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if ($revision === null) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $this->dataService->lockRevision($revision);

        if ($revision->getEndTime() && ! $this->isGranted('ROLE_SUPER')) {
            throw new ElasticmsException($this->translator->trans(
                'log.data.revision.only_super_can_finalize_an_archive',
                LoggingContext::read($revision),
                EMSCoreBundle::TRANS_DOMAIN
            ));
        }

        if ($request->isMethod('GET')) {
            $this->loadAutoSavedVersion($revision);
        }

        $form = $this->createForm(RevisionType::class, $revision, [
            'has_clipboard' => $request->getSession()->has('ems_clipboard'),
            'has_copy' => $this->isGranted('ROLE_COPY_PASTE'),
            'raw_data' => $revision->getRawData(),
        ]);

        $this->logger->debug('Revision\'s form created');


        /**little trick to reorder collection*/
        $requestRevision = $request->request->get('revision');
        $this->reorderCollection($requestRevision);
        $request->request->set('revision', $requestRevision);
        /**end little trick to reorder collection*/


        $form->handleRequest($request);

        $this->logger->debug('Revision request form handled');


        if ($form->isSubmitted()) {//Save, Finalize or Discard
            if (empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
                $this->logger->error('log.data.revision.not_completed_request', LoggingContext::read($revision));

                return $this->redirectToRoute('data.revisions', [
                    'ouuid' => $revision->getOuuid(),
                    'type' => $revision->getContentType()->getName(),
                    'revisionId' => $revision->getId(),
                ]);
            }


            $revision->setAutoSave(null);
            if (!array_key_exists('discard', $request->request->get('revision'))) {//Save, Copy, Paste or Finalize
                //Save anyway
                /** @var Revision $revision */
                $revision = $form->getData();

                $this->logger->debug('Revision extracted from the form');


                $objectArray = $revision->getRawData();

                if (array_key_exists('paste', $request->request->get('revision'))) {//Paste
                    $this->logger->notice('log.data.revision.paste', LoggingContext::update($revision));
                    $objectArray = array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
                    $this->logger->debug('Paste data have been merged');
                }

                if (array_key_exists('copy', $request->request->get('revision'))) {//Copy
                    $request->getSession()->set('ems_clipboard', $objectArray);
                    $this->logger->notice('log.data.document.copy', LoggingContext::update($revision));
                }

                $revision->setRawData($objectArray);


                $this->dataService->setMetaFields($revision);

                $this->logger->debug('Revision before persist');
                $em->persist($revision);
                $em->flush();

                $this->logger->debug('Revision after persist flush');

                if (array_key_exists('publish', $request->request->get('revision'))) {//Finalize
                    $revision = $this->dataService->finalizeDraft($revision, $form);
                    if (count($form->getErrors()) === 0) {
                        if ($revision->getOuuid()) {
                            return $this->redirectToRoute('data.revisions', [
                                'ouuid' => $revision->getOuuid(),
                                'type' => $revision->getContentType()->getName(),
                            ]);
                        } else {
                            return $this->redirectToRoute('revision.edit', [
                                'revisionId' => $revision->getId(),
                            ]);
                        }
                    }
                }
            }

            //if paste or copy
            if (array_key_exists('paste', $request->request->get('revision')) || array_key_exists('copy', $request->request->get('revision'))) {//Paste or copy
                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revisionId,
                ]);
            }

            //if Save or Discard
            if (!array_key_exists('publish', $request->request->get('revision'))) {
                if (null != $revision->getOuuid()) {
                    if (count($form->getErrors()) === 0 && $revision->getContentType()->isAutoPublish()) {
                        $this->publishService->silentPublish($revision);
                    }

                    return $this->redirectToRoute('data.revisions', [
                        'ouuid' => $revision->getOuuid(),
                        'type' => $revision->getContentType()->getName(),
                        'revisionId' => $revision->getId(),
                    ]);
                } else {
                    return $this->redirectToRoute('data.draft_in_progress', [
                        'contentTypeId' => $revision->getContentType()->getId(),
                    ]);
                }
            }
        } else {
            $objectArray = $revision->getRawData();
            $isValid = $this->dataService->isValid($form, $revision->getContentType()->getParentField(), $objectArray);
            if (!$isValid) {
                $this->logger->warning('log.data.revision.can_finalized', LoggingContext::update($revision));
            }
        }

        if ($revision->getContentType()->isAutoPublish()) {
            $this->logger->warning('log.data.revision.auto_save_off_with_auto_publish', LoggingContext::update($revision));
        }


        $objectArray = $revision->getRawData();
        $this->dataService->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid(), false, false);

        if ($revision->getOuuid()) {
            $messageLog = "log.data.revision.start_edit";
        } else {
            $messageLog = "log.data.revision.start_edit_new_document";
        }
        $this->logger->info($messageLog, LoggingContext::read($revision));

        return $this->render('@EMSCore/data/edit-revision.html.twig', [
            'revision' => $revision,
            'form' => $form->createView(),
            'stylesSets' => $this->wysiwygStylesSetService->getStylesSets(),
        ]);
    }

    private function loadAutoSavedVersion(Revision $revision)
    {
        if (null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->logger->warning('log.data.revision.load_from_auto_save', LoggingContext::read($revision));
        }
    }

    private function reorderCollection(&$input)
    {
        if (is_array($input) && !empty($input)) {
            $keys = array_keys($input);
            if (is_int($keys[0])) {
                sort($keys);
                $temp = [];
                $loop0 = 0;
                foreach ($input as $item) {
                    $temp[$keys[$loop0]] = $item;
                    ++$loop0;
                }
                $input = $temp;
            }
            foreach ($input as &$elem) {
                $this->reorderCollection($elem);
            }
        }
    }
}
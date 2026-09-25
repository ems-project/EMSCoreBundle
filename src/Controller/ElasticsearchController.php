<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Form\ExportDocuments;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ExportDocumentsType;
use EMS\CoreBundle\Repository\MessengerMessagesRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class ElasticsearchController extends AbstractController
{
    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly IndexService $indexService,
        private readonly ElasticaService $elasticaService,
        private readonly DataService $dataService,
        private readonly AssetExtractorService $assetExtractorService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly JobService $jobService,
        private readonly TranslatorInterface $translator,
        private readonly SerializerInterface $serializer,
        private readonly MessengerMessagesRepository $messengerMessagesRepository,
        private readonly ?string $healthCheckAllowOrigin,
        private readonly string $templateNamespace
    ) {
    }

    public function addAlias(string $name, Request $request): Response
    {
        $form = $this->createFormBuilder([])->add('name', IconTextType::class, [
            'icon' => 'fa fa-key',
            'required' => true,
        ])->add('save', SubmitEmsType::class, [
            'label' => 'Add',
            'icon' => 'fa fa-plus',
            'attr' => [
                'class' => 'btn btn-primary pull-right',
                'data-testid' => 'btn-action-save',
            ],
        ])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $aliasName = $form->get('name')->getData();
            $this->indexService->updateAlias($aliasName, [], [$name]);

            $this->logger->messageNotice(t('message.alias_added', [
                'alias_name' => $aliasName,
                'index_name' => $name,
            ], 'emsco-core'));

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return $this->render(\sprintf('@%s/elasticsearch/add-alias.html.twig', $this->templateNamespace), [
            'form' => $form->createView(),
            'name' => $name,
            'title' => t('type.title_create', ['type' => 'alias', 'label' => $name], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'alias'], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->environments()->add(
                label: t('key.orphan_indexes', [], 'emsco-core'),
                icon: 'fa fa-chain-broken',
                route: Routes::ADMIN_ELASTIC_ORPHAN
            )->add(t('type.title_create', ['type' => 'alias', 'label' => $name], 'emsco-core')),
            'notice' => t('type.notice_message', ['type' => 'alias'], 'emsco-core'),
        ]);
    }

    public function status(Request $request, string $_format, bool $detailed = true): Response
    {
        if ($detailed && !$this->authorizationChecker->isGranted('ROLE_USER')) {
            $detailed = false;
        }
        $statusCode = 200;
        $context = [];
        try {
            $health = $this->elasticaService->getClusterHealth();
            $context['cluster'] = $detailed ? $health : null;
            $context['cluster']['status'] = $status = $health['status'] ?? 'red';
            $context['cluster']['title'] = $this->translator->trans('cluster.status', ['color' => $status], 'emsco-core');
            if ('red' === $status) {
                $statusCode = 500;
            }
        } catch (\Throwable $throwable) {
            $status = 'red';
            $context['cluster']['title'] = $throwable->getMessage();
            $statusCode = 503;
        }
        $context['status'] = $status;
        $context['title'] = $context['cluster']['title'];

        try {
            $jobFailed = $this->jobService->countFailed();
            $jobPending = $this->jobService->countPending();
            $jobStatus = $jobFailed > 0 ? 'yellow' : 'green';
            $jobPendingLimit = (int) $request->query->get('jobs_pending_limit', '0');
            if ($jobPendingLimit > 0 && $jobPending >= $jobPendingLimit) {
                $jobStatus = 'red';
                $statusCode = 500;
            }
            $jobFailedLimit = (int) $request->query->get('jobs_failed_limit', '0');
            if ($jobFailedLimit > 0 && $jobFailed >= $jobFailedLimit) {
                $jobStatus = 'red';
                $statusCode = 500;
            }
            $context['jobs']['pending'] = $jobPending;
            $context['jobs']['failed'] = $jobFailed;
            $context['jobs']['status'] = $jobStatus;
        } catch (\Throwable) {
        }

        try {
            $messageFailed = $this->messengerMessagesRepository->errorCount();
            $messageQueue = $this->messengerMessagesRepository->waitingCount() - $messageFailed;
            $messageStatus = $messageFailed > 0 ? 'yellow' : 'green';
            $busQueueLimit = (int) $request->query->get('bus_queue_limit', '0');
            if ($busQueueLimit > 0 && $messageQueue >= $busQueueLimit) {
                $messageStatus = 'red';
                $statusCode = 500;
            }
            $busFailedLimit = (int) $request->query->get('bus_failed_limit', '0');
            if ($busFailedLimit > 0 && $messageFailed >= $busFailedLimit) {
                $messageStatus = 'red';
                $statusCode = 500;
            }
            $context['bus']['queue'] = $messageQueue;
            $context['bus']['failed'] = $messageFailed;
            $context['bus']['status'] = $messageStatus;
        } catch (\Throwable) {
        }

        if ($detailed) {
            $context['cluster'] = \array_merge($context['cluster'], $this->elasticaService->getClusterInfo());

            $context['certificate'] = $this->dataService->getCertificateInfo();
            $context['certificate']['title'] = $this->translator->trans('certificate.status', ['color' => $context['certificate']['status'] ?? 'red'], 'emsco-core');

            try {
                $context['asset_extractor'] = $this->assetExtractorService->hello();
                $context['asset_extractor']['status'] = 'green';
                $context['asset_extractor']['title'] = $this->translator->trans('asset_extractor.status', ['color' => 'green'], 'emsco-core');
            } catch (\Exception $e) {
                $context['asset_extractor'] = [
                    'status' => 'red',
                    'title' => $this->translator->trans('asset_extractor.status', ['color' => 'red'], 'emsco-core'),
                    'message' => $e->getMessage(),
                ];
            }
        }

        $htmlTemplate = \sprintf('@%s/elasticsearch/status.html.twig', $this->templateNamespace);
        $response = match ($_format) {
            'json' => new JsonResponse(\array_filter(\array_merge($context, [
                'body' => $this->renderBlock($htmlTemplate, 'status', $context)->getContent(),
            ]))),
            'xml' => new Response($this->serializer->serialize($context, 'xml'), Response::HTTP_OK, ['Content-Type' => 'application/xml']),
            default => $this->render($htmlTemplate, \array_filter(\array_merge($context, [
                'title' => t('title.systems_status', [], 'emsco-core'),
                'subTitle' => t('title.systems_status_tagline', [], 'emsco-core'),
                'breadcrumb' => new Navigation()->add(
                    label: t('title.systems_status', [], 'emsco-core'),
                    icon: 'fa-solid fa-stethoscope',
                ),
            ]))),
        };
        $response->setStatusCode($statusCode);

        $allowOrigin = $this->healthCheckAllowOrigin;
        if (\is_string($allowOrigin) && '' !== $allowOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        }

        return $response;
    }

    public function export(Request $request, ContentType $contentType): Response
    {
        $exportDocuments = new ExportDocuments($contentType, $this->generateUrl('emsco_search_export', ['contentType' => $contentType]), '{}');
        $form = $this->createForm(ExportDocumentsType::class, $exportDocuments);
        $form->handleRequest($request);

        /** @var ExportDocuments */
        $exportDocuments = $form->getData();
        $command = \sprintf(
            "%s %s %s '%s'%s --environment=%s --baseUrl=%s",
            Commands::CONTENT_TYPE_EXPORT,
            $contentType->getName(),
            $exportDocuments->getFormat(),
            $exportDocuments->getQuery(),
            $exportDocuments->isWithBusinessKey() ? ' --withBusinessId' : '',
            $exportDocuments->getEnvironment(),
            '//'.$request->getHttpHost()
        );
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user object');
        }

        $job = $this->jobService->createCommand($user, $command);

        return $this->redirectToRoute('emsco_job_status', [
            'job' => $job->getId(),
        ]);
    }
}

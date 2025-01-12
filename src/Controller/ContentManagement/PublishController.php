<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\NonUniqueResultException;
use Elastica\Query\AbstractQuery;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Command\Environment\AlignCommand;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Json;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublishController extends AbstractController
{
    public function __construct(
        private readonly PublishService $publishService,
        private readonly JobService $jobService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly SearchService $searchService,
        private readonly ElasticaService $elasticaService,
        private readonly string $templateNamespace)
    {
    }

    public function publishTo(Revision $revisionId, Environment $envId): Response
    {
        $revision = $revisionId;
        $environment = $envId;

        $contentType = $revisionId->giveContentType();
        if ($contentType->getDeleted()) {
            throw new \RuntimeException('Content type deleted');
        }

        try {
            $this->publishService->publish($revision, $environment);
        } catch (NonUniqueResultException) {
            throw new NotFoundHttpException('Revision not found');
        }

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'ouuid' => $revision->getOuuid(),
            'type' => $contentType->getName(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function unPublish(Revision $revisionId, Environment $envId): RedirectResponse
    {
        $contentType = $revisionId->getContentType();
        if (null === $contentType) {
            throw new \RuntimeException('Content type not found');
        }
        if ($contentType->getDeleted()) {
            throw new \RuntimeException('Content type deleted');
        }

        $this->publishService->unpublish($revisionId, $envId);

        return $this->redirectToRoute(Routes::VIEW_REVISIONS, [
            'ouuid' => $revisionId->getOuuid(),
            'type' => $contentType->getName(),
            'revisionId' => $revisionId->getId(),
        ]);
    }

    public function publishSearchResult(Request $request): Response
    {
        if (!$this->isGranted('ROLE_PUBLISHER')) {
            throw new AccessDeniedHttpException();
        }
        $search = new Search();
        $searchForm = $this->createForm(SearchFormType::class, $search, [
            'method' => 'GET',
        ]);
        $requestBis = clone $request;

        $requestBis->setMethod('GET');
        $searchForm->handleRequest($requestBis);

        if (1 !== \count($search->getEnvironments())) {
            throw new NotFoundHttpException('Environment not found');
        }
        if (1 !== \count($search->getContentTypes())) {
            throw new NotFoundHttpException('Content type not found');
        }

        $environment = $this->environmentService->getAliasByName($search->getEnvironments()[0]);
        $contentType = $this->contentTypeService->getByName($search->getContentTypes()[0]);

        if (!$environment instanceof Environment) {
            throw new NotFoundHttpException('Environment not found');
        }
        if (!$contentType instanceof ContentType) {
            throw new NotFoundHttpException('Content type not found');
        }

        $data = [];
        $builder = $this->createFormBuilder($data);
        $builder->add('toEnvironment', EnvironmentPickerType::class, [
            'managedOnly' => true,
            'ignore' => [$environment->getName()],
        ])->add('publish', SubmitEmsType::class, [
            'attr' => [
                'class' => 'btn btn-primary btn-md',
            ],
            'icon' => 'glyphicon glyphicon-open',
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);
        $search->setEnvironments([$environment->getName()]);
        $search->setContentTypes([$contentType->getName()]);
        $emsSearch = $this->searchService->generateSearch($search);
        $total = $this->elasticaService->count($emsSearch);
        $query = $emsSearch->getQuery();

        if ($form->isSubmitted()) {
            $user = $this->getUser();

            if (!$user instanceof UserInterface) {
                throw new NotFoundHttpException('User not found');
            }

            $query = Json::encode(null === $query ? [] : ($query instanceof AbstractQuery ? $query->toArray() : $query));
            $command = [
                Commands::ENVIRONMENT_ALIGN,
                $environment->getName(),
                $form->get('toEnvironment')->getData(),
                \sprintf('--%s', AlignCommand::OPTION_FORCE),
                \sprintf("--%s='%s'", AlignCommand::OPTION_SEARCH_QUERY, $query),
            ];

            $job = $this->jobService->createCommand($user, \implode(' ', $command));

            return $this->redirectToRoute('job.status', [
                'job' => $job->getId(),
            ]);
        }

        return $this->render("@$this->templateNamespace/publish/publish-search-result.html.twig", [
            'form' => $form->createView(),
            'fromEnvironment' => $environment,
            'contentType' => $contentType,
            'counter' => $total,
        ]);
    }
}

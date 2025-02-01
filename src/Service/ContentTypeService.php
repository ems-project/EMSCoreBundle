<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elastica\Exception\ResponseException;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Entity\EntityInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\ContentType\ContentTypeRoles;
use EMS\CoreBundle\Core\ContentType\ContentTypeUnreferenced;
use EMS\CoreBundle\Core\ContentType\ViewDefinition;
use EMS\CoreBundle\Core\UI\Menu;
use EMS\CoreBundle\Core\UI\MenuEntry;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\Helper\JsonClass;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\ContentTypeAlreadyExistException;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\FieldTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class ContentTypeService implements EntityServiceInterface
{
    private const string CONTENT_TYPE_AGGREGATION_NAME = 'content-types';
    /** @var ContentType[] */
    protected array $orderedContentTypes = [];
    /** @var ContentType[] */
    protected array $contentTypeArrayByName = [];

    public function __construct(
        private readonly ContentTypeRepository $contentTypeRepository,
        protected Registry $doctrine,
        protected LocalizedLoggerInterface $logger,
        private readonly Mapping $mappingService,
        private readonly ElasticaService $elasticaService,
        private readonly EnvironmentService $environmentService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RevisionRepository $revisionRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly RouterInterface $router,
        private readonly ?string $circleContentTypeName
    ) {
    }

    public function getChildByPath(FieldType $fieldType, string $path, bool $skipVirtualFields = false): FieldType|false
    {
        $elem = \explode('.', $path);

        /** @var FieldType $child */
        foreach ($fieldType->getChildren() as $child) {
            if (!$child->getDeleted()) {
                $type = $child->getType();
                if ($skipVirtualFields && $type::isVirtual($child->getOptions())) {
                    $fieldTypeByPath = $this->getChildByPath($child, $path, $skipVirtualFields);
                    if ($fieldTypeByPath) {
                        return $fieldTypeByPath;
                    }
                } elseif ($child->getName() == $elem[0]) {
                    if (\strpos($path, '.')) {
                        $fieldTypeByPath = $this->getChildByPath($fieldType, \substr($path, \strpos($path, '.') + 1), $skipVirtualFields);
                        if ($fieldTypeByPath) {
                            return $fieldTypeByPath;
                        }
                    }

                    return $child;
                }
            }
        }

        return false;
    }

    private function loadEnvironment(): void
    {
        if ([] === $this->orderedContentTypes) {
            /** @var ContentTypeRepository $contentTypeRepository */
            $contentTypeRepository = $this->doctrine->getManager()->getRepository(ContentType::class);
            /** @var ContentType[] $orderedContentTypes */
            $orderedContentTypes = $contentTypeRepository->findBy(['deleted' => false], ['orderKey' => 'ASC']);
            $this->orderedContentTypes = $orderedContentTypes;
            $this->contentTypeArrayByName = [];
            /** @var ContentType $contentType */
            foreach ($this->orderedContentTypes as $contentType) {
                $this->contentTypeArrayByName[$contentType->getName()] = $contentType;
            }
        }
    }

    public function persist(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        $em->persist($contentType);
        $em->flush();
    }

    public function persistField(FieldType $fieldType): void
    {
        $em = $this->doctrine->getManager();
        $em->persist($fieldType);
        $em->flush();
    }

    /**
     * @param array<mixed> $newStructure
     */
    public function reorderFields(ContentType $contentType, array $newStructure): void
    {
        $em = $this->doctrine->getManager();
        $contentType->getFieldType()->reorderFields($newStructure);

        $em->persist($contentType);
        $em->flush();

        $this->logger->notice('service.contenttype.reordered', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
        ]);
    }

    public function getIndex(ContentType $contentType, ?Environment $environment = null): string
    {
        $environment ??= $contentType->giveEnvironment();

        return $environment->getAlias();
    }

    public function updateMapping(ContentType $contentType, ?string $envs = null): void
    {
        try {
            $body = $this->environmentService->getIndexAnalysisConfiguration();
            if (null === $envs) {
                $envs = \array_reduce($this->environmentService->getManagedEnvironement(), function ($envs, $item) use ($contentType, $body) {
                    /* @var Environment $item */
                    $index = $this->getIndex($contentType, $item);
                    $this->mappingService->createIndex($index, $body, $item->getAlias());

                    if (isset($envs)) {
                        $envs .= ','.$index;
                    } else {
                        $envs = $index;
                    }

                    return $envs;
                });
            }

            if (isset($envs)) {
                if ($this->mappingService->putMapping($contentType, $envs)) {
                    $contentType->setDirty(false);
                } else {
                    $contentType->setDirty(true);
                }
            }

            $this->contentTypeRepository->save($contentType);
        } catch (ResponseException $e) {
            $contentType->setDirty(true);
            $message = $e->getMessage();
            if (!empty($e->getPrevious())) {
                $message = $e->getPrevious()->getMessage();
            }

            $this->logger->error('service.contenttype.update_mapping_exception', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                'environments' => $envs,
                'elasticsearch_error' => $message,
            ]);
        }
    }

    public function updateMappingByIds(string ...$ids): void
    {
        $contentTypes = $this->contentTypeRepository->getByIds(...$ids);
        $dirtyContentTypes = \array_filter($contentTypes, static fn (ContentType $c) => $c->getDirty());

        foreach ($dirtyContentTypes as $dirtyContentType) {
            $this->updateMapping($dirtyContentType);
        }
    }

    public function activateByIds(string ...$ids): void
    {
        $contentTypes = $this->contentTypeRepository->getByIds(...$ids);
        $deactivatedContentTypes = \array_filter($contentTypes, static fn (ContentType $c) => !$c->isActive());

        foreach ($deactivatedContentTypes as $deactivatedContentType) {
            $deactivatedContentType->setActive(true);
            $this->contentTypeRepository->save($deactivatedContentType);
        }
    }

    public function deactivateByIds(string ...$ids): void
    {
        $contentTypes = $this->contentTypeRepository->getByIds(...$ids);
        $activeContentTypes = \array_filter($contentTypes, static fn (ContentType $c) => $c->isActive());

        foreach ($activeContentTypes as $activeContentType) {
            $activeContentType->setActive(false);
            $this->contentTypeRepository->save($activeContentType);
        }
    }

    public function giveByName(string $name): ContentType
    {
        $this->loadEnvironment();
        $contentType = $this->contentTypeArrayByName[$name] ?? null;

        if (null === $contentType) {
            throw new \RuntimeException(\sprintf('Could not find contentType with the name %s', $name));
        }

        return $contentType;
    }

    public function getByName(string $name): ContentType|false
    {
        $this->loadEnvironment();

        return $this->contentTypeArrayByName[$name] ?? false;
    }

    /** @return ContentType[] */
    public function getByNames(string ...$names): array
    {
        return $this->contentTypeRepository->findBy(['name' => $names]);
    }

    /**
     * @return array<mixed>
     */
    public function getAllByAliases(): array
    {
        $this->loadEnvironment();
        $contentTypeAliases = [];

        foreach ($this->orderedContentTypes as $contentType) {
            $environmentAlias = $contentType->giveEnvironment()->getAlias();

            if (!isset($contentTypeAliases[$environmentAlias])) {
                $contentTypeAliases[$environmentAlias] = [];
            }
            $contentTypeAliases[$environmentAlias][$contentType->getName()] = $contentType;
        }

        return $contentTypeAliases;
    }

    /**
     * @return string[]
     */
    public function getAllDefaultEnvironmentNames(): array
    {
        $this->loadEnvironment();
        $out = [];
        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->giveEnvironment()->getAlias()])) {
                $out[$contentType->giveEnvironment()->getName()] = $contentType->giveEnvironment()->getName();
            }
        }

        return \array_keys($out);
    }

    /**
     * @return ContentType[]
     */
    public function getAllGrantedForPublication(): array
    {
        $contentTypes = [];
        foreach ($this->getAll() as $contentType) {
            if ($contentType->getDeleted()) {
                continue;
            }

            $publishRole = $contentType->role(ContentTypeRoles::PUBLISH);
            if ($this->authorizationChecker->isGranted($publishRole)) {
                $contentTypes[] = $contentType;
            }
        }

        return $contentTypes;
    }

    public function getAllAliases(): string
    {
        $this->loadEnvironment();
        $out = [];

        foreach ($this->orderedContentTypes as $contentType) {
            if (!isset($out[$contentType->giveEnvironment()->getAlias()])) {
                $out[$contentType->giveEnvironment()->getAlias()] = $contentType->giveEnvironment()->getAlias();
            }
        }

        return \implode(',', $out);
    }

    /**
     * @return ContentType[]
     */
    public function getAll(): array
    {
        $this->loadEnvironment();

        return $this->orderedContentTypes;
    }

    /**
     * @return string[]
     */
    public function getAllNames(): array
    {
        $this->loadEnvironment();
        $out = [];
        /** @var Environment $env */
        foreach ($this->orderedContentTypes as $env) {
            $out[] = $env->getName();
        }

        return $out;
    }

    public function getAllTypes(): string
    {
        $this->loadEnvironment();

        return \implode(',', \array_keys($this->contentTypeArrayByName));
    }

    public function updateFromJson(ContentType $contentType, string $json, bool $isDeleteExitingTemplates, bool $isDeleteExitingViews): ContentType
    {
        $this->deleteFields($contentType);
        if ($isDeleteExitingTemplates) {
            $this->deleteTemplates($contentType);
        }
        if ($isDeleteExitingViews) {
            $this->deleteViews($contentType);
        }

        $environment = $contentType->getEnvironment();
        if (!$environment instanceof Environment) {
            throw new NotFoundHttpException('Environment not found');
        }

        $updatedContentType = $this->contentTypeFromJson($json, $environment, $contentType);

        return $this->importContentType($updatedContentType);
    }

    public function contentTypeFromJson(string $json, ?Environment $environment = null, ?ContentType $contentType = null): ContentType
    {
        $meta = JsonClass::fromJsonString($json);
        $contentType = $meta->jsonDeserialize($contentType);
        if (!$contentType instanceof ContentType) {
            throw new \Exception(\sprintf('ContentType expected for import, got %s', $meta->getClass()));
        }

        if (null !== $environment) {
            $contentType->setEnvironment($environment);

            return $contentType;
        }

        $environmentName = Json::decode($json)['properties']['environment'] ?? null;
        if (\is_string($environmentName)) {
            $environment = $this->environmentService->giveByName($environmentName);
        } else {
            $environment = $this->getFirstEnvironment();
        }
        $contentType->setEnvironment($environment);

        return $contentType;
    }

    private function deleteFields(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        $contentType->unsetFieldType();
        /** @var FieldTypeRepository $fieldRepo */
        $fieldRepo = $em->getRepository(FieldType::class);
        $fields = $fieldRepo->findBy([
            'contentType' => $contentType,
        ]);
        foreach ($fields as $field) {
            $em->remove($field);
        }
        $em->flush();
    }

    private function deleteTemplates(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        foreach ($contentType->getTemplates() as $template) {
            $contentType->removeTemplate($template);
        }
        /** @var TemplateRepository $templateRepo */
        $templateRepo = $em->getRepository(Template::class);
        $templates = $templateRepo->findBy([
            'contentType' => $contentType,
        ]);
        foreach ($templates as $template) {
            $em->remove($template);
        }

        $em->flush();
    }

    private function deleteViews(ContentType $contentType): void
    {
        $em = $this->doctrine->getManager();
        foreach ($contentType->getViews() as $view) {
            $contentType->removeView($view);
        }
        /** @var ViewRepository $viewRepo */
        $viewRepo = $em->getRepository(View::class);
        $views = $viewRepo->findBy([
            'contentType' => $contentType,
        ]);
        foreach ($views as $view) {
            $em->remove($view);
        }

        $em->flush();
    }

    public function importContentType(ContentType $contentType): ContentType
    {
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);

        $previousContentType = $this->getByName($contentType->getName());
        if ($previousContentType instanceof ContentType && $previousContentType->getId() !== $contentType->getId()) {
            throw new ContentTypeAlreadyExistException('ContentType with name '.$contentType->getName().' already exists');
        }

        $contentType->reset($contentTypeRepository->nextOrderKey());
        $this->persist($contentType);

        return $contentType;
    }

    /**
     * @return ContentTypeUnreferenced[]
     */
    public function getUnreferencedContentTypes(): array
    {
        $unreferencedContentTypes = [];
        foreach ($this->environmentService->getUnmanagedEnvironments() as $environment) {
            try {
                $unreferencedContentTypes = [
                    ...$unreferencedContentTypes,
                    ...$this->getUnreferencedContentTypesPerEnvironment($environment),
                ];
            } catch (\Throwable $e) {
                $this->logger->messageError(t(
                    message: 'log.error.content_type_add_unreferenced',
                    parameters: ['environment' => $environment->getName(), 'error' => $e->getMessage()],
                    domain: 'emsco-core'
                ));
            }
        }

        return $unreferencedContentTypes;
    }

    /**
     * @return ContentTypeUnreferenced[]
     */
    private function getUnreferencedContentTypesPerEnvironment(Environment $environment): array
    {
        $search = new Search([$environment->getAlias()]);
        $search->setSize(0);
        $search->addTermsAggregation(self::CONTENT_TYPE_AGGREGATION_NAME, EMSSource::FIELD_CONTENT_TYPE, 30);
        $resultSet = $this->elasticaService->search($search);
        $aggregationBuckets = $resultSet->getAggregation(self::CONTENT_TYPE_AGGREGATION_NAME)['buckets'] ?? [];
        $unreferencedContentTypes = [];
        foreach ($aggregationBuckets as $aggregationBucket) {
            $name = $aggregationBucket['key'] ?? null;

            if (null === $name || false !== $this->getByName($name)) {
                continue;
            }

            $unreferencedContentTypes[] = new ContentTypeUnreferenced(
                name: $name,
                environment: $environment,
                count: (int) ($aggregationBucket['doc_count'] ?? 0)
            );
        }

        return $unreferencedContentTypes;
    }

    public function update(ContentType $contentType, bool $mustBeReset = true): void
    {
        $em = $this->doctrine->getManager();
        /** @var ContentTypeRepository $contentTypeRepository */
        $contentTypeRepository = $em->getRepository(ContentType::class);
        if ($mustBeReset) {
            $contentType->reset($contentTypeRepository->nextOrderKey());
        }
        $this->persist($contentType);
        $em->flush();
    }

    public function getCircleContentType(): ?ContentType
    {
        return $this->contentTypeArrayByName[$this->circleContentTypeName] ?? null;
    }

    public function getContentTypeMenu(): Menu
    {
        $menu = new Menu('views.elements.sidebar-menu-html.content-types');
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            throw new \RuntimeException('Unexpected null token');
        }
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user type');
        }
        $this->loadEnvironment();
        $isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
        $temp = $this->revisionRepository->draftCounterGroupedByContentType($user->getCircles(), $isAdmin);
        $counters = [];
        foreach ($temp as $item) {
            $counters[$item['content_type_id']] = $item['counter'];
        }
        $circleContentType = $this->getCircleContentType();

        foreach ($this->orderedContentTypes as $contentType) {
            $roles = $contentType->getRoles();

            if ($contentType->getDeleted()
                || !$contentType->getActive()
                || (!$this->authorizationChecker->isGranted($roles[ContentTypeRoles::VIEW])) && !$contentType->getRootContentType()) {
                continue;
            }

            [$routeOverview, $routeOverviewParams] = $this->getRedirectOverviewRoute($contentType);
            $menuEntry = new MenuEntry(
                label: $contentType->getPluralName(),
                icon: $contentType->getIcon() ?? 'fa fa-book',
                route: $routeOverview,
                routeParameters: $routeOverviewParams,
                color: $contentType->getColor()
            );
            if (isset($counters[$contentType->getId()])) {
                $menuEntry->setBadge((string) $counters[$contentType->getId()]);
            }
            $this->addMenuSearchLinks($contentType, $menuEntry, $circleContentType, $user);
            $this->addMenuViewLinks($contentType, $menuEntry);
            $this->addDraftInProgressLink($contentType, $menuEntry);

            if ($this->authorizationChecker->isGranted($roles[ContentTypeRoles::SHOW_LINK_CREATE])
                && $this->authorizationChecker->isGranted($roles[ContentTypeRoles::CREATE])) {
                $createLink = $menuEntry->addChild('sidebar_menu.content_type.create', 'fa fa-plus', Routes::DATA_ADD, ['contentType' => $contentType->getId()]);
                $createLink->setTranslation([
                    '%name%' => $contentType->getSingularName(),
                ]);
            }
            if ($this->authorizationChecker->isGranted($roles[ContentTypeRoles::TRASH])) {
                $trashLink = $menuEntry->addChild('sidebar_menu.content_type.trash', 'fa fa-trash', Routes::DATA_TRASH, ['contentType' => $contentType->getId()]);
                $trashLink->setTranslation([]);
            }
            if ($menuEntry->hasChildren()) {
                $menu->addMenuEntry($menuEntry);
            }
        }

        return $menu;
    }

    private function addMenuSearchLinks(ContentType $contentType, MenuEntry $menuEntry, ?ContentType $circleContentType, UserInterface $user): void
    {
        $roles = $contentType->getRoles();

        if (!$this->authorizationChecker->isGranted($roles[ContentTypeRoles::SHOW_LINK_SEARCH])) {
            return;
        }

        $search = $menuEntry->addChild('sidebar_menu.content_type.search', 'fa fa-search', Routes::DATA_DEFAULT_VIEW, ['type' => $contentType->getName()]);
        $search->setTranslation(['%plural%' => $contentType->getPluralName()]);

        if (null === $circleContentType || null === $contentType->getCirclesField() || '' === $contentType->getCirclesField() || empty($user->getCircles())) {
            return;
        }

        $inMyCircle = $menuEntry->addChild('sidebar_menu.content_type.search_in_my_circle', $circleContentType->getIcon() ?? '', Routes::DATA_IN_MY_CIRCLE_VIEW, ['name' => $contentType->getName()]);
        $inMyCircle->setTranslation([
            '%name%' => \count($user->getCircles()) > 1 ? $circleContentType->getPluralName() : $circleContentType->getSingularName(),
        ]);
    }

    private function addMenuViewLinks(ContentType $contentType, MenuEntry $menuEntry): void
    {
        foreach ($contentType->getViews() as $view) {
            if (null !== $view->getRole() && !$this->authorizationChecker->isGranted($view->getRole())) {
                continue;
            }
            if ('ems.view.data_link' === $view->getType()) {
                continue;
            }
            $menuEntry->addChild($view->getLabel(), $view->getIcon() ?? '', $view->isPublic() ? Routes::DATA_PUBLIC_VIEW : Routes::DATA_PRIVATE_VIEW, ['viewId' => $view->getId()]);
        }
    }

    private function addDraftInProgressLink(ContentType $contentType, MenuEntry $menuEntry): void
    {
        if (!$contentType->giveEnvironment()->getManaged() || !$menuEntry->hasBadge() || !$this->authorizationChecker->isGranted($contentType->role(ContentTypeRoles::EDIT))) {
            return;
        }

        $draftInProgress = $menuEntry->addChild('sidebar_menu.content_type.draft_in_progress', 'fa fa-fire', Routes::DRAFT_IN_PROGRESS, ['contentTypeId' => $contentType->getId()]);
        $draftInProgress->setTranslation([]);
        $draftInProgress->setBadge($menuEntry->getBadge(), $contentType->getColor());
    }

    #[\Override]
    public function isSortable(): bool
    {
        return true;
    }

    /**
     * @return ContentType[]
     */
    #[\Override]
    public function get(int $from, int $size, ?string $orderField, string $orderDirection, string $searchValue, mixed $context = null): array
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }

        return $this->getContentTypeRepository()->get($from, $size, $orderField, $orderDirection, $searchValue);
    }

    #[\Override]
    public function getEntityName(): string
    {
        return 'content-type';
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getAliasesName(): array
    {
        return [
            'content-types',
            'contenttype',
            'contenttypes',
            'Content-Type',
            'Content-Types',
        ];
    }

    #[\Override]
    public function count(string $searchValue = '', mixed $context = null): int
    {
        if (null !== $context) {
            throw new \RuntimeException('Unexpected non-null object');
        }
        $contentTypeRepository = $this->getContentTypeRepository();

        return $contentTypeRepository->counter($searchValue);
    }

    #[\Override]
    public function getByItemName(string $name): ?EntityInterface
    {
        $contentTypeRepository = $this->getContentTypeRepository();

        return $contentTypeRepository->findByName($name);
    }

    #[\Override]
    public function updateEntityFromJson(EntityInterface $entity, string $json): EntityInterface
    {
        if (!$entity instanceof ContentType) {
            throw new \RuntimeException('unexpected non ContentType entity');
        }

        return $this->updateFromJson($entity, $json, false, false);
    }

    #[\Override]
    public function createEntityFromJson(string $json, ?string $name = null): EntityInterface
    {
        $contentType = $this->contentTypeFromJson($json);
        if (null !== $name && $contentType->getName() !== $name) {
            throw new \RuntimeException(\sprintf('Unexpected mismatched content type name : %s vs %s', $name, $contentType->getName()));
        }

        return $this->importContentType($contentType);
    }

    protected function getContentTypeRepository(): ContentTypeRepository
    {
        $em = $this->doctrine->getManager();
        $contentTypeRepository = $em->getRepository(ContentType::class);
        if (!$contentTypeRepository instanceof ContentTypeRepository) {
            throw new \RuntimeException('Unexpected non ContentTypeRepository object');
        }

        return $contentTypeRepository;
    }

    /**
     * @return array<string, ?string>
     */
    public function getVersionDefault(ContentType $contentType): array
    {
        if (!$contentType->hasVersionTags()) {
            return [];
        }

        $versionTags = $contentType->getVersionTags();
        $defaultVersion = \array_shift($versionTags);
        $defaultVersionLabel = $this->translator->trans(
            'field.revision_version_tag',
            ['%version_tag%' => $defaultVersion],
            'emsco-core'
        );

        return [$defaultVersionLabel => $defaultVersion];
    }

    /**
     * @return array<string, ?string>
     */
    public function getVersionTagsByContentType(ContentType $contentType, ?bool $notBlankNewVersion = false): array
    {
        if (!$contentType->hasVersionTags()) {
            return [];
        }

        $versionTags = $contentType->getVersionTags();
        $versionTagsLabels = \array_map(fn (string $versionTag) => $this->translator->trans(
            'field.revision_version_tag',
            ['%version_tag%' => $versionTag],
            'emsco-core'
        ), $versionTags);

        $emptyLabel = $this->translator->trans('field.revision_version_tag_empty', [], 'emsco-core');
        $emptyValue = ($notBlankNewVersion ? Revision::VERSION_BLANK : null);

        return [$emptyLabel => $emptyValue] + \array_combine($versionTagsLabels, $versionTags);
    }

    /**
     * @return array<string, string|null>
     */
    public function getVersionTags(): array
    {
        $versionTags = [];
        foreach ($this->getAll() as $contentType) {
            if ($contentType->isActive()) {
                $versionTags = [...$versionTags, ...$this->getVersionTagsByContentType($contentType)];
            }
        }

        return \array_unique($versionTags);
    }

    public function hasSearch(?bool $isDirty = null, ?bool $isActive = null): bool
    {
        $qb = $this->contentTypeRepository->makeQueryBuilder(
            isActive: $isActive,
            isDirty: $isDirty
        );

        return $qb->select('count(c.id)')->getQuery()->getSingleScalarResult() > 0;
    }

    public function reorderByIds(string ...$ids): void
    {
        $counter = 1;
        foreach ($ids as $id) {
            $contentType = $this->contentTypeRepository->getById($id);
            $contentType->setOrderKey($counter++);
            $this->contentTypeRepository->save($contentType);
        }
    }

    public function softDelete(ContentType $contentType): void
    {
        if ($contentType->getDeleted()) {
            return;
        }

        $contentType->setActive(false)->setDeleted(true);
        $this->contentTypeRepository->save($contentType);

        $this->logger->messageNotice(t('log.notice.content_type_deleted', ['contentType' => $contentType->getName()], 'emsco-core'));
    }

    public function softDeleteById(string ...$ids): void
    {
        $contentTypes = $this->contentTypeRepository->getByIds(...$ids);
        foreach ($contentTypes as $contentType) {
            $this->softDelete($contentType);
        }
    }

    #[\Override]
    public function deleteByItemName(string $name): string
    {
        $contentTypeRepository = $this->getContentTypeRepository();
        $contentType = $this->getByItemName($name);
        if (null === $contentType) {
            throw new \RuntimeException(\sprintf('Entity %s not found', $name));
        }
        if (!$contentType instanceof ContentType) {
            throw new \RuntimeException('Unexpected non ContentType object');
        }
        $id = $contentType->getId();
        $contentTypeRepository->delete($contentType);

        return (string) $id;
    }

    public function switchDefaultEnvironment(ContentType $contentType, Environment $target, string $username): void
    {
        $this->revisionRepository->switchEnvironments($contentType, $target, $username);
        $contentType->setEnvironment($target);
        $this->persist($contentType);
    }

    /**
     * @return array{0: string, 1: array<string, string|int>}
     */
    public function getRedirectOverviewRoute(ContentType $contentType): array
    {
        $defaultOverviewView = $contentType->getViewByDefinition(ViewDefinition::DEFAULT_OVERVIEW);

        if ($defaultOverviewView) {
            return [
                $defaultOverviewView->isPublic() ? Routes::DATA_PUBLIC_VIEW : Routes::DATA_PRIVATE_VIEW,
                ['viewId' => $defaultOverviewView->getId()],
            ];
        }

        return [Routes::DATA_DEFAULT_VIEW, ['type' => $contentType->getName()]];
    }

    public function redirectOverview(ContentType $contentType): RedirectResponse
    {
        [$routeName, $routeParams] = $this->getRedirectOverviewRoute($contentType);

        return new RedirectResponse($this->router->generate($routeName, $routeParams));
    }

    private function getFirstEnvironment(): Environment
    {
        $firstEnvironment = null;
        foreach ($this->environmentService->getEnvironments() as $environment) {
            if (!$environment->getManaged() || $environment->getSnapshot()) {
                continue;
            }
            $firstEnvironment = $environment;
            break;
        }
        if (null === $firstEnvironment) {
            throw new \RuntimeException('At least one managed environment is required');
        }

        return $firstEnvironment;
    }
}

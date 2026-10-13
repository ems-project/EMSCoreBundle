<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Admin;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Core\UI\Page\Page;
use EMS\CoreBundle\Entity\ManagedAlias;
use EMS\CoreBundle\Form\Form\ManagedAliasType;
use EMS\CoreBundle\Repository\ManagedAliasRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AliasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use function Symfony\Component\Translation\t;

class EnvironmentManagedAliasController extends AbstractController
{
    private const string PAGE_TEMPLATE = 'page/managed_alias.html.twig';

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly AliasService $aliasService,
        private readonly ManagedAliasRepository $managedAliasRepository,
        private readonly string $instanceId,
    ) {
    }

    public function add(Request $request): Page|RedirectResponse
    {
        $managedAlias = new ManagedAlias();
        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));

            $this->logger->messageNotice(t('message.managed_alias_created', [
                'managed_alias_label' => $managedAlias->getLabel(),
            ], 'emsco-core'));

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('action.add_managed_alias', [], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->environments()->add(t('action.add_managed_alias', [], 'emsco-core')),
        ], self::PAGE_TEMPLATE);
    }

    public function edit(Request $request, ManagedAlias $managedAlias): Page|RedirectResponse
    {
        $managedAlias = $this->aliasService->getManagedAliasByName($managedAlias->getName());

        $form = $this->createForm(ManagedAliasType::class, $managedAlias);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($managedAlias, $this->getIndexActions($form));

            $this->logger->messageNotice(t('message.managed_alias_updated', [
                'managed_alias_label' => $managedAlias->getLabel(),
            ], 'emsco-core'));

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return new Page([
            'form' => $form->createView(),
            'title' => t('action.edit_managed_alias', [], 'emsco-core'),
            'breadcrumb' => Navigation::admin()->environments()->add(t('action.edit_managed_alias', [], 'emsco-core')),
        ], self::PAGE_TEMPLATE);
    }

    public function remove(ManagedAlias $managedAlias): RedirectResponse
    {
        $this->aliasService->removeAlias($managedAlias->getAlias());
        $this->managedAliasRepository->delete($managedAlias);
        $this->logger->messageNotice(t('message.managed_alias_deleted', [
            'managed_alias_label' => $managedAlias->getLabel(),
        ], 'emsco-core'));

        return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
    }

    /**
     * @param array<mixed> $actions
     */
    private function save(ManagedAlias $managedAlias, array $actions): void
    {
        $managedAlias->setAlias($this->instanceId);
        $this->aliasService->updateAlias($managedAlias->getAlias(), $actions);
        $this->managedAliasRepository->update($managedAlias);
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return array<mixed>
     */
    private function getIndexActions(FormInterface $form): array
    {
        $actions = [];
        $submitted = $form->get('indexes')->getData();
        $indexes = \array_keys($form->getConfig()->getOption('indexes'));

        foreach ($indexes as $index) {
            if (\in_array($index, $submitted)) {
                $actions['add'][] = $index;
            } else {
                $actions['remove'][] = $index;
            }
        }

        return $actions;
    }
}

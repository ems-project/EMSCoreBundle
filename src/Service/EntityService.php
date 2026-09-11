<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

abstract class EntityService
{
    public function __construct(
        protected Registry $doctrine,
        protected LocalizedLoggerInterface $logger,
        protected TranslatorInterface $translator
    ) {
    }

    /**
     * @return class-string
     */
    abstract protected function getRepositoryIdentifier(): string;

    abstract protected function getEntityName(): string;

    /**
     * @param FormInterface<mixed> $reorderForm
     */
    public function reorder(FormInterface $reorderForm): void
    {
        /** @var string $items */
        $items = $reorderForm->getData()['items'];
        $order = Json::decode($items);
        $i = 1;
        foreach ($order as $id) {
            $item = $this->get($id);

            if ($item && \method_exists($item, 'setOrderKey')) {
                $item->setOrderKey($i++);
                $this->save($item);
            }
        }
    }

    /**
     * @return object[]
     */
    public function getAll(): array
    {
        return $this->getRepository()->findAll();
    }

    /**
     * @return EntityRepository<object>
     */
    private function getRepository(): ObjectRepository
    {
        $em = $this->doctrine->getManager();

        return $em->getRepository($this->getRepositoryIdentifier());
    }

    public function get(int $id): ?object
    {
        return $this->getRepository()->find($id);
    }

    public function create(object $entity): void
    {
        $repository = $this->getRepository();
        $count = (int) $repository->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult();

        if (\method_exists($entity, 'setOrderKey')) {
            $entity->setOrderKey(100 + $count);
            $this->update($entity);

            $this->logger->messageNotice(t('message.entity_created', [
                'type' => $this->getEntityName(),
                'name' => \method_exists($entity, 'getName') ? $entity->getName() : $entity::class,
            ], 'emsco-core'));
        }
    }

    public function save(object $entity): void
    {
        $this->update($entity);
        $this->logger->messageNotice(t('message.entity_updated', [
            'type' => $this->getEntityName(),
            'name' => \method_exists($entity, 'getName') ? $entity->getName() : $entity::class,
        ], 'emsco-core'));
    }

    private function update(object $entity): void
    {
        $em = $this->doctrine->getManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(object $entity): void
    {
        $em = $this->doctrine->getManager();
        $em->remove($entity);
        $em->flush();

        $this->logger->messageNotice(t('message.entity_deleted', [
            'type' => $this->getEntityName(),
            'name' => \method_exists($entity, 'getName') ? $entity->getName() : $entity::class,
        ], 'emsco-core'));
    }
}

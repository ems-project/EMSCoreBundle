<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\SortOption;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class EntityService
{
    protected Registry $doctrine;
    protected LoggerInterface $logger;
    protected TranslatorInterface $translator;

    public function __construct(Registry $doctrine, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    abstract protected function getRepositoryIdentifier();

    abstract protected function getEntityName();

    public function reorder(FormInterface $reorderForm)
    {
        $order = \json_decode($reorderForm->getData()['items'], true);
        $i = 1;
        foreach ($order as $id) {
            $item = $this->get($id);
            $item->setOrderKey($i++);
            $this->save($item);
        }
    }

    /**
     * @return array<mixed>
     */
    public function getAll()
    {
        /** @var SortOption[] $items */
        $items = $this->getRepository()->findAll();

        return $items;
    }

    /**
     * @return ObjectRepository
     */
    private function getRepository()
    {
        $em = $this->doctrine->getManager();

        return $em->getRepository($this->getRepositoryIdentifier());
    }

    /**
     * @param int $id
     *
     * @return SortOption|null
     */
    public function get($id)
    {
        /** @var SortOption|null $item */
        $item = $this->getRepository()->find($id);

        return $item;
    }

    public function create($entity)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $count = $repository->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult();

        $entity->setOrderKey(100 + $count);
        $this->update($entity);

        $this->logger->notice('service.entity.created', [
            'entity_type' => $this->getEntityName(),
            'entity_name' => $entity->getName(),
        ]);
    }

    public function save($entity)
    {
        $this->update($entity);
        $this->logger->notice('service.entity.updated', [
            'entity_type' => $this->getEntityName(),
            'entity_name' => $entity->getName(),
        ]);
    }

    private function update($entity)
    {
        $em = $this->doctrine->getManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove($entity)
    {
        $em = $this->doctrine->getManager();
        $em->remove($entity);
        $em->flush();
        $this->logger->notice('service.entity.deleted', [
            'entity_type' => $this->getEntityName(),
            'entity_name' => $entity->getName(),
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\WysiwygStylesSet;

class WysiwygStylesSetRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygStylesSet::class);
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }

    public function update(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->persist($styleSet);
        $this->getEntityManager()->flush();
    }

    public function delete(WysiwygStylesSet $styleSet): void
    {
        $this->getEntityManager()->remove($styleSet);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?WysiwygStylesSet
    {
        $styleSet = $this->find($id);
        if (null !== $styleSet && !$styleSet instanceof WysiwygStylesSet) {
            throw new \RuntimeException('Unexpected wysiwyg style set type');
        }

        return $styleSet;
    }

    public function getByName(string $name): ?WysiwygStylesSet
    {
        $styleSet = $this->findOneBy(['name' => $name]);
        if (null !== $styleSet && !$styleSet instanceof WysiwygStylesSet) {
            throw new \RuntimeException('Unexpected style set type');
        }

        return $styleSet;
    }
}

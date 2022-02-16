<?php

namespace EMS\CoreBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use EMS\CoreBundle\Entity\WysiwygProfile;

class WysiwygProfileRepository extends ServiceEntityRepository
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry, WysiwygProfile::class);
    }

    /**
     * @return WysiwygProfile[]
     */
    public function findAll(): array
    {
        return parent::findBy([], ['orderKey' => 'asc']);
    }

    public function update(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->persist($profile);
        $this->getEntityManager()->flush();
    }

    public function delete(WysiwygProfile $profile): void
    {
        $this->getEntityManager()->remove($profile);
        $this->getEntityManager()->flush();
    }

    public function findById(int $id): ?WysiwygProfile
    {
        $wysiwygProfile = $this->find($id);
        if (null !== $wysiwygProfile && !$wysiwygProfile instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected wysiwyg profile type');
        }

        return $wysiwygProfile;
    }

    public function getByName(string $name): ?WysiwygProfile
    {
        $profile = $this->findOneBy(['name' => $name]);
        if (null !== $profile && !$profile instanceof WysiwygProfile) {
            throw new \RuntimeException('Unexpected profile type');
        }

        return $profile;
    }
}

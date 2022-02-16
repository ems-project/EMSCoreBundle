<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Repository\I18nRepository;

class I18nService
{
    private I18nRepository $repository;

    public function __construct(I18nRepository $i18nRepository)
    {
        $this->repository = $i18nRepository;
    }

    /**
     * @param array<string>|null $filters
     */
    public function counter(array $filters = null): int
    {
        $identifier = null;

        if (null != $filters && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }

        return $this->repository->countWithFilter($identifier);
    }

    public function delete(I18n $i18n): void
    {
        $this->repository->delete($i18n);
    }

    /**
     * @param array<string>|null $filters
     *
     * @return iterable|I18n[]
     */
    public function findAll(int $from, int $limit, array $filters = null): iterable
    {
        $identifier = null;

        if (null != $filters && isset($filters['identifier']) && !empty($filters['identifier'])) {
            $identifier = $filters['identifier'];
        }

        return $this->repository->findByWithFilter($limit, $from, $identifier);
    }
}

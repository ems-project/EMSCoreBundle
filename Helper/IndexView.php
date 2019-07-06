<?php

namespace EMS\CoreBundle\Helper;

use Doctrine\ORM\EntityRepository;

class IndexView
{
    /** @var EntityRepository  */
    private $repository;

    /** @var string  */
    private $entityName;

    /** @var string  */
    private $transKey;

    /** @var string  */
    private $icon;

    public function __construct(string $entityName, EntityRepository $repository, string $icon)
    {
        $this->repository = $repository;
        $this->entityName = $entityName;
        $this->icon = $icon;

        $split = preg_split('/\\\\/', $this->entityName);
        $this->transKey = strtolower(array_pop($split));
    }

    public function getTitleKey(): string
    {
        return $this->transKey.'.index.title';
    }

    public function getSubTitleKey(): string
    {
        return $this->transKey.'.index.subtitle';
    }

    public function getAddKey(): string
    {
        return $this->transKey.'.add';
    }

    public function getMissingKey(): string
    {
        return $this->transKey.'.missing';
    }

    public function getIndexRoute(): string
    {
        return 'ems_'.$this->transKey.'_index';
    }

    public function getAddRoute(): string
    {
        return 'ems_'.$this->transKey.'_add';
    }

    public function getEditRoute(): string
    {
        return 'ems_'.$this->transKey.'_edit';
    }

    public function getRepository(): EntityRepository
    {
        return $this->repository;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }
}

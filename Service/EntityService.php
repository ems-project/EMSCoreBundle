<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Entity\SortOption;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;
use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;

abstract class EntityService
{
    /**@var Registry $doctrine */
    protected $doctrine;
    /**@var Session $session*/
    protected $session;
    /**@var TranslatorInterface $translator */
    protected $translator;
    
    public function __construct(Registry $doctrine, Session $session, TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
        $this->translator= $translator;
    }
    
    abstract protected function getRepositoryIdentifier();
    abstract protected function getEntityName();
    
    
    public function reorder(Form $reorderform)
    {
        $order = json_decode($reorderform->getData()['items'], true);
        $i = 1;
        foreach ($order as $id) {
            $item = $this->get($id);
            $item->setOrderKey($i++);
            $this->save($item);
        }
    }
    
    /**
     *
     * @param integer $id
     * @return SortOption|null
     */
    public function getAll()
    {
        return $this->getRepository()->findAll();
    }
    
    /**
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    private function getRepository()
    {
        $em = $this->doctrine->getManager();
        return $em->getRepository($this->getRepositoryIdentifier());
    }
    
    /**
     *
     * @param integer $id
     * @return SortOption|null
     */
    public function get($id)
    {
        
        $item = $this->getRepository()->find($id);
        
        return $item;
    }
    
    public function create($entity)
    {
        $count = $this->getRepository()->createQueryBuilder('a')
            ->select('COUNT(a)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $entity->setOrderKey(100+$count);
        $this->update($entity);
        $this->session->getFlashBag()->add('notice', $this->translator->trans('%type% %name% has been created', ['%type%' => $this->getEntityName(), '%name%' => $entity->getName()], EMSCoreExtension::TRANS_DOMAIN));
    }
    
    public function save($entity)
    {
        $this->update($entity);
        $this->session->getFlashBag()->add('notice', $this->translator->trans('%type% %name% has been updated', ['%type%' => $this->getEntityName(), '%name%' => $entity->getName()], EMSCoreExtension::TRANS_DOMAIN));
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
        $this->session->getFlashBag()->add('notice', $this->translator->trans('%type% %name% has been deleted', ['%type%' => $this->getEntityName(), '%name%' => $entity->getName()], EMSCoreExtension::TRANS_DOMAIN));
    }
}

<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Repository\WysiwygStylesSetRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class WysiwygStylesSetService
{
    /** @var Registry */
    private $doctrine;
    /** @var LoggerInterface */
    private $logger;
    /** @var TranslatorInterface */
    private $translator;

    public function __construct(Registry $doctrine, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    public function getStylesSets()
    {
        $em = $this->doctrine->getManager();
        /** @var WysiwygStylesSetRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygStylesSet');

        $profiles = $repository->findAll();

        return $profiles;
    }

    /**
     * @param int $id
     *
     * @return WysiwygStylesSet|null
     */
    public function get($id)
    {
        $em = $this->doctrine->getManager();
        /** @var WysiwygStylesSetRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygStylesSet');

        $profile = $repository->find($id);

        return $profile;
    }

    public function save(WysiwygStylesSet $stylesSet)
    {
        $em = $this->doctrine->getManager();
        $em->persist($stylesSet);
        $em->flush();
        $this->logger->notice('service.wysiwyg_styles_set.updated', [
            'wysiwyg_styles_set_name' => $stylesSet->getName(),
        ]);
    }

    public function remove(WysiwygStylesSet $stylesSet)
    {
        $name = $stylesSet->getName();
        $em = $this->doctrine->getManager();
        $em->remove($stylesSet);
        $em->flush();
        $this->logger->notice('service.wysiwyg_styles_set.deleted', [
            'wysiwyg_styles_set_name' => $name,
        ]);
    }
}

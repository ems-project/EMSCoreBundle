<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatorInterface;

class WysiwygProfileService
{
    /** @var Registry $doctrine */
    private $doctrine;
    /** @var LoggerInterface $logger */
    private $logger;
    /** @var TranslatorInterface $translator */
    private $translator;

    public function __construct(Registry $doctrine, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    public function getProfiles()
    {
        $em = $this->doctrine->getManager();
        /** @var WysiwygProfileRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygProfile');

        $profiles = $repository->findAll();

        return $profiles;
    }

    /**
     * @param int $id
     *
     * @return WysiwygProfile|null
     */
    public function get($id)
    {
        $em = $this->doctrine->getManager();
        /** @var WysiwygProfileRepository */
        $repository = $em->getRepository('EMSCoreBundle:WysiwygProfile');

        $profile = $repository->find($id);

        return $profile;
    }

    public function saveProfile(WysiwygProfile $profile)
    {
        $em = $this->doctrine->getManager();
        $em->persist($profile);
        $em->flush();
        $this->logger->notice('service.wysiwyg_profile.updated', [
            'profile_name' => $profile->getName(),
        ]);
    }

    public function remove(WysiwygProfile $profile)
    {
        $em = $this->doctrine->getManager();
        $em->remove($profile);
        $em->flush();
        $this->logger->notice('service.wysiwyg_profile.deleted', [
            'profile_name' => $profile->getName(),
        ]);
    }
}

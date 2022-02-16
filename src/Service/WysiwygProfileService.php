<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\WysiwygProfile;
use EMS\CoreBundle\Repository\WysiwygProfileRepository;
use Psr\Log\LoggerInterface;

class WysiwygProfileService
{
    private WysiwygProfileRepository $wysiwygProfileRepository;
    private LoggerInterface $logger;

    public function __construct(WysiwygProfileRepository $wysiwygProfileRepository, LoggerInterface $logger)
    {
        $this->wysiwygProfileRepository = $wysiwygProfileRepository;
        $this->logger = $logger;
    }

    /**
     * @return WysiwygProfile[]
     */
    public function getProfiles(): array
    {
        $profiles = $this->wysiwygProfileRepository->findAll();

        return $profiles;
    }

    public function getById(int $id): ?WysiwygProfile
    {
        return $this->wysiwygProfileRepository->findById($id);
    }

    public function saveProfile(WysiwygProfile $profile)
    {
        $this->wysiwygProfileRepository->update($profile);
        $this->logger->notice('service.wysiwyg_profile.updated', [
            'profile_name' => $profile->getName(),
        ]);
    }

    public function remove(WysiwygProfile $profile)
    {
        $this->wysiwygProfileRepository->delete($profile);
        $this->logger->notice('service.wysiwyg_profile.deleted', [
            'profile_name' => $profile->getName(),
        ]);
    }
}

<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\WysiwygStylesSet;
use EMS\CoreBundle\Repository\WysiwygStylesSetRepository;
use Psr\Log\LoggerInterface;

class WysiwygStylesSetService
{
    private WysiwygStylesSetRepository $wysiwygStylesSetRepository;
    private LoggerInterface $logger;

    public function __construct(WysiwygStylesSetRepository $wysiwygStylesSetRepository, LoggerInterface $logger)
    {
        $this->wysiwygStylesSetRepository = $wysiwygStylesSetRepository;
        $this->logger = $logger;
    }

    /**
     * @return WysiwygStylesSet[]
     */
    public function getStylesSets(): array
    {
        static $stylesSets = null;
        if (null !== $stylesSets) {
            return $stylesSets;
        }
        $stylesSets = $this->wysiwygStylesSetRepository->findAll();

        return $stylesSets;
    }

    public function getByName(?string $name): ?WysiwygStylesSet
    {
        if (null === $name) {
            foreach ($this->getStylesSets() as $stylesSet) {
                return $stylesSet;
            }

            return null;
        }

        return $this->wysiwygStylesSetRepository->getByName($name);
    }

    public function getById(int $id): ?WysiwygStylesSet
    {
        return $this->wysiwygStylesSetRepository->findById($id);
    }

    public function save(WysiwygStylesSet $stylesSet)
    {
        $this->wysiwygStylesSetRepository->update($stylesSet);
        $this->logger->notice('service.wysiwyg_styles_set.updated', [
            'wysiwyg_styles_set_name' => $stylesSet->getName(),
        ]);
    }

    public function remove(WysiwygStylesSet $stylesSet)
    {
        $name = $stylesSet->getName();
        $this->wysiwygStylesSetRepository->delete($stylesSet);
        $this->logger->notice('service.wysiwyg_styles_set.deleted', [
            'wysiwyg_styles_set_name' => $name,
        ]);
    }
}

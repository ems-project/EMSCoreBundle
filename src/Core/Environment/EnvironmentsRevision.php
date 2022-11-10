<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Environment;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;

class EnvironmentsRevision
{
    public Environment $default;
    /** @var Collection<int, Environment> */
    public Collection $environments;
    /** @var Collection<int, Environment> */
    public Collection $unpublish;
    /** @var Collection<int, Environment> */
    public Collection $publish;

    /**
     * @param Collection<int, Environment> $userPublishEnvironments
     */
    public function __construct(Revision $revision, Collection $userPublishEnvironments, bool $hasPublishRole)
    {
        $environments = $revision->getEnvironments();

        $this->default = $revision->giveContentType()->giveEnvironment();
        $this->environments = $environments;
        $this->unpublish = new ArrayCollection();
        $this->publish = new ArrayCollection();

        if ($hasPublishRole) {
            $publishEnvironments = $userPublishEnvironments->filter(function (Environment $e) {
                return $e->getManaged() && $e !== $this->default;
            });

            $this->publish = $publishEnvironments->filter(fn (Environment $e) => !$environments->contains($e));
            $this->unpublish = $publishEnvironments->filter(fn (Environment $e) => $environments->contains($e));
        }
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Template;

class NotificationFilter
{
    /** @var Collection<int, Template> */
    public Collection $template;
    /** @var Environment[] */
    public array $environment = [];
    /** @var Collection<int, ContentType> */
    public Collection $contentType;

    public function __construct()
    {
        $this->template = new ArrayCollection();
        $this->contentType = new ArrayCollection();
    }
}

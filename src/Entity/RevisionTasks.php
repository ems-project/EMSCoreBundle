<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
final class RevisionTasks
{
    /**
     * @ORM\Column(name="current_id", type="string", length=255, nullable=true)
     */
    private ?string $currentId;

    /**
     * @var null|string[]
     *
     * @ORM\Column(name="planned_ids", type="json", nullable=true)
     */
    private ?array $plannedIds = [];

    /**
     * @var null|string[]
     *
     * @ORM\Column(name="approved_ids", type="json", nullable=true)
     */
    private ?array $approvedIds = [];
}
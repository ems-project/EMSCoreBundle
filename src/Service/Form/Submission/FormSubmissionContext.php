<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service\Form\Submission;

readonly class FormSubmissionContext
{
    /**
     * @param string[]|null $names
     * @param string[]|null $instances
     * @param string[]|null $locales
     */
    public function __construct(
        private ?\DateTimeImmutable $from = null,
        private ?\DateTimeImmutable $to = null,
        private ?array $names = null,
        private ?array $instances = null,
        private ?array $locales = null,
    ) {
    }

    public function getFrom(): ?\DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): ?\DateTimeImmutable
    {
        return $this->to;
    }

    /**
     * @return string[]|null
     */
    public function getNames(): ?array
    {
        return $this->names;
    }

    /**
     * @return string[]|null
     */
    public function getInstances(): ?array
    {
        return $this->instances;
    }

    /**
     * @return string[]|null
     */
    public function getLocales(): ?array
    {
        return $this->locales;
    }
}

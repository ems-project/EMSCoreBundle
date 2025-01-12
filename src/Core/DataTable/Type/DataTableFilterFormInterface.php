<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable\Type;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

interface DataTableFilterFormInterface
{
    /**
     * @return FormInterface<mixed>
     */
    public function filterFormBuild(FormFactoryInterface $formFactory, mixed $context): FormInterface;

    /**
     * @param FormInterface<mixed> $filterForm
     */
    public function filterFormAddToContext(FormInterface $filterForm, mixed $context): mixed;
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;

trait CoreControllerTrait
{
    /**
     * @param FormInterface<mixed> $form
     */
    protected function getClickedButtonName(FormInterface $form): ?string
    {
        if (!$form instanceof Form) {
            return null;
        }

        $clickedButton = $form->getClickedButton();

        return $clickedButton instanceof FormInterface ? $clickedButton->getName() : null;
    }
}

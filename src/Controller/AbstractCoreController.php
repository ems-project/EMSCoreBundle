<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;

abstract class AbstractCoreController extends AbstractController
{
    protected function getClickedButtonName(FormInterface $form): ?string
    {
        if (!$form instanceof Form) {
            return null;
        }

        $clickedButton = $form->getClickedButton();

        return $clickedButton instanceof FormInterface ? $clickedButton->getName() : null;
    }
}

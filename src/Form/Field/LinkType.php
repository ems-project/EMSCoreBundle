<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ButtonTypeInterface;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<mixed>
 */
class LinkType extends AbstractType implements ButtonTypeInterface
{
    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['route'] = $options['route'];
        $view->vars['route_params'] = $options['route_params'];
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('route')
            ->setAllowedTypes('route', 'string')
            ->setDefaults(['route_params' => []])
            ->setAllowedTypes('route_params', 'array');
    }

    #[\Override]
    public function getParent(): string
    {
        return ButtonType::class;
    }
}

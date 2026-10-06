<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ColTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('col', null);
        $resolver->setAllowedTypes('col', ['null', 'int']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['col'] = $options['col'];
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocaleFormExtension extends AbstractTypeExtension
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['locale' => null]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['locale'] = $options['locale'];
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}

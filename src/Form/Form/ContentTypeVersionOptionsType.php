<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Core\ContentType\Version\VersionOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class ContentTypeVersionOptionsType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed>        $options
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(VersionOptions::DATES_READ_ONLY, CheckboxType::class, ['required' => false])
            ->add(VersionOptions::DATES_INTERVAL_ONE_DAY, CheckboxType::class, ['required' => false])
            ->add(VersionOptions::NOT_BLANK_NEW_VERSION, CheckboxType::class, ['required' => false])
        ;
    }
}

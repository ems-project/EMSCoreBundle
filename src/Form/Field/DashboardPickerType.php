<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardPickerType extends Select2Type
{
    public function __construct(private readonly DashboardService $dashboardService, private readonly TranslatorInterface $translator)
    {
        parent::__construct();
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->dashboardService->getIds(),
            'attr' => [
                'data-live-search' => true,
            ],
            'choice_attr' => fn ($value) => [
                'data-icon' => $this->translator->trans(\implode('.', [$value, 'icon']), [], EMSCoreBundle::TRANS_TWIG_DOMAIN),
            ],
            'choice_value' => fn ($value) => $value,
            'choice_label' => fn ($value) => $this->translator->trans(\implode('.', [$value, 'label']), [], EMSCoreBundle::TRANS_TWIG_DOMAIN),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
use EMS\CoreBundle\EMSCoreBundle;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardPickerType extends SelectPickerType
{
    private DashboardService $dashboardService;
    private TranslatorInterface $translator;

    public function __construct(DashboardService $dashboardService, TranslatorInterface $translator)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->dashboardService->getIds(),
            'attr' => [
                    'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $id) {
                $icon = $this->translator->trans(\implode('.', [$id, 'icon']), [], EMSCoreBundle::TRANS_TWIG_DOMAIN);
                $label = $this->translator->trans(\implode('.', [$id, 'label']), [], EMSCoreBundle::TRANS_TWIG_DOMAIN);

                return [
                    'data-content' => \sprintf('<div class="text"><i class="%s"></i>&nbsp;&nbsp;%s</div>', $icon, \htmlentities($label)),
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }
}

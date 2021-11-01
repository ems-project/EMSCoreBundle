<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Field;

use EMS\CoreBundle\Core\Dashboard\DashboardService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DashboardPickerType extends SelectPickerType
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        parent::__construct();
        $this->dashboardService = $dashboardService;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => $this->dashboardService->getIds(),
            'attr' => [
                    'data-live-search' => true,
            ],
            'choice_attr' => function ($category, $key, $id) {
                $dashboard = $this->dashboardService->get($id);

                return [
                    'data-content' => \sprintf('<div class="text"><i class="%s"></i>&nbsp;&nbsp;%s</div>', $dashboard->getIcon(), \htmlentities($dashboard->getLabel())),
                ];
            },
            'choice_value' => function ($value) {
                return $value;
            },
        ]);
    }
}

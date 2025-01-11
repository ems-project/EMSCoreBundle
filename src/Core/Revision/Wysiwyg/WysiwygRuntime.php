<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Wysiwyg;

use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class WysiwygRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private WysiwygStylesSetService $wysiwygStylesSetService,
        private UserManager $userManager,
        private UrlGeneratorInterface $urlGenerator,
        private DashboardManager $dashboardManager,
    ) {
    }

    public function getInfo(): string
    {
        return Json::encode([
            'config' => \array_merge_recursive($this->getDefaultConfig(), $this->getConfig()),
            'styles' => $this->getStyles(),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getConfig(): array
    {
        $profile = $this->userManager->getUser()?->getWysiwygProfile();

        if (null === $profile || null === $profileConfig = $profile->getConfig()) {
            return [];
        }

        $config = Json::decode($profileConfig);

        if (isset($config['ems']['paste'])) {
            $config['emsAjaxPaste'] = $this->urlGenerator->generate('emsco_wysiwyg_ajax_paste', [
                'wysiwygProfileId' => $profile->getId(),
            ]);
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        $config = [
            'imageUploadUrl' => $this->urlGenerator->generate('ems_image_upload_url'),
            'imageBrowser_listUrl' => $this->urlGenerator->generate('ems_images_index'),
            'ems_filesUrl' => $this->urlGenerator->generate('ems_core_uploaded_file_wysiwyg_index'),
        ];

        foreach (Dashboard::DASHBOARD_BROWSERS as $definition) {
            if ($dashboard = $this->dashboardManager->getDefinition($definition)) {
                $config['emsBrowsers'][$definition] = [
                    'label' => $dashboard->getLabel(),
                    'url' => $this->urlGenerator->generate('emsco_dashboard_browse', [
                        'dashboardName' => $dashboard->getName(),
                    ]),
                ];
            }
        }

        return $config;
    }

    /**
     * @return array<mixed>
     */
    private function getStyles(): array
    {
        $styles = [];
        $styleSets = $this->wysiwygStylesSetService->getStylesSets();

        foreach ($styleSets as $styleSet) {
            $styles[] = [
                'name' => $styleSet->getName(),
                'config' => Json::decode($styleSet->getConfig()),
            ];
        }

        return $styles;
    }
}

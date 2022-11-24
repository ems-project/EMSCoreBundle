<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Revision\Wysiwyg;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Service\UserService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class WysiwygRuntime implements RuntimeExtensionInterface
{
    private WysiwygStylesSetService $wysiwygStylesSetService;
    private UserService $userService;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        WysiwygStylesSetService $wysiwygStylesSetService,
        UserService $userService,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->wysiwygStylesSetService = $wysiwygStylesSetService;
        $this->userService = $userService;
        $this->urlGenerator = $urlGenerator;
    }

    public function getInfo(): string
    {
        $config = $this->getConfig();
        $config['imageUploadUrl'] = $this->urlGenerator->generate('ems_image_upload_url');
        $config['imageBrowser_listUrl'] = $this->urlGenerator->generate('ems_images_index');
        $config['ems_filesUrl'] = $this->urlGenerator->generate('ems_core_uploaded_file_wysiwyg_index');

        return Json::encode([
            'config' => $config,
            'styles' => $this->getStyles(),
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getConfig(): array
    {
        try {
            $user = $this->userService->getCurrentUser();
        } catch (\RuntimeException $e) {
            return [];
        }

        $profile = $user->getWysiwygProfile();

        if ($profile && null !== $profile->getConfig()) {
            return Json::decode($profile->getConfig());
        }

        $wysiwygOptions = $user->getWysiwygOptions();

        return null !== $wysiwygOptions && Json::isJson($wysiwygOptions) ? Json::decode($wysiwygOptions) : [];
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

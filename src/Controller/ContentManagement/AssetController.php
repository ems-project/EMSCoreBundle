<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AssetController extends AbstractController
{
    private Processor $processor;
    /** @var array<string, mixed> */
    protected array $assetConfig;

    /**
     * @param array<string, mixed> $assetConfig
     */
    public function __construct(Processor $processor, array $assetConfig)
    {
        $this->processor = $processor;
        $this->assetConfig = $assetConfig;
    }

    /**
     * @return Response
     *
     * @Route("/data/asset/{hash_config}/{hash}/{filename}" , name="ems_asset", methods={"GET","HEAD"})
     * @Route("/public/asset/{hash_config}/{hash}/{filename}" , name="emsco_asset_public", methods={"GET","HEAD"})
     */
    public function assetAction(string $hash, string $hash_config, string $filename, Request $request)
    {
        $this->closeSession($request);
        try {
            return $this->processor->getResponse($request, $hash, $hash_config, $filename);
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(\sprintf('File %s/%s/%s not found', $hash_config, $hash, $filename));
        }
    }

    /**
     * @deprecated
     * @Route("/asset/{processor}/{hash}", name="ems_asset_processor", methods={"GET","HEAD"})
     */
    public function assetProcessorAction(Request $request, string $processor, string $hash): Response
    {
        $this->closeSession($request);
        $assetConfig = $this->assetConfig[$processor] ?? [];
        if (!\is_array($assetConfig)) {
            throw new \RuntimeException('Unexpected asset config type');
        }

        $filename = $processor;
        $quality = \intval($assetConfig[EmsFields::ASSET_CONFIG_QUALITY] ?? 0);
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === ($assetConfig[EmsFields::ASSET_CONFIG_TYPE] ?? null) && !isset($assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE])) {
            $assetConfig[EmsFields::ASSET_CONFIG_MIME_TYPE] = 0 === $quality ? 'image/png' : 'image/jpeg';
        }
        if (EmsFields::ASSET_CONFIG_TYPE_IMAGE === ($assetConfig[EmsFields::ASSET_CONFIG_TYPE] ?? null)) {
            $filename .= 0 === $quality ? '.png' : '.jpg';
        }

        $assetConfig = \array_intersect_key($assetConfig, Config::getDefaults());
        $config = $this->processor->configFactory($hash, $assetConfig);

        return $this->processor->getStreamedResponse($request, $config, $filename, false);
    }

    private function closeSession(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->save();
        }
    }
}

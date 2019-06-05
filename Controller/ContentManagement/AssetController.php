<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CoreBundle\Twig\AppExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AssetController extends AbstractController
{
    /** @var Processor */
    private $processor;
    /** @var AppExtension */
    private $appExtension;

    public function __construct(Processor $processor, AppExtension $appExtension)
    {
        $this->processor = $processor;
        $this->appExtension = $appExtension;
    }

    /**
     * @param string $hash
     * @param string $hash_config
     * @param string $filename
     * @param Request $request
     * @return Response
     *
     * @Route("/data/asset/{hash_config}/{hash}/{filename}" , name="ems_asset", methods={"GET","HEAD"})
     * @Route("/public/asset/{hash_config}/{hash}/{filename}" , name="emsco_asset_public", methods={"GET","HEAD"})
     */
    public function assetAction(string $hash, string $hash_config, string $filename, Request $request)
    {
        try {
            return $this->processor->getResponse($request, $hash, $hash_config, $filename);
        } catch (NotFoundException $e) {
            throw new NotFoundHttpException(sprintf('File %s/%s/%s not found', $hash_config, $hash, $filename));
        }
    }


    /**
     * @deprecated
     * @param Request $request
     * @param string $processor
     * @param string $hash
     * @return Response
     * @Route("/asset/{processor}/{hash}", name="ems_asset_processor", methods={"GET","HEAD"})
     */
    public function assetProcessorAction(Request $request, string $processor, string $hash): Response
    {
        @trigger_error(sprintf('The "%s::assetProcessorAction" controller is deprecated. Used "%s::assetAction" instead.', AssetController::class, AssetController::class), E_USER_DEPRECATED);

        return $this->redirect($this->appExtension->assetPath([
            EmsFields::CONTENT_FILE_HASH_FIELD => $hash,
            EmsFields::CONTENT_FILE_NAME_FIELD => $request->query->get('name', 'filename'),
            EmsFields::CONTENT_MIME_TYPE_FIELD => $request->query->get('type', 'application/octet-stream'),
        ], $processor, [], 'emsco_asset_public'));
    }
}

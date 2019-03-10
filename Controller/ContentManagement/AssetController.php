<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AssetController extends AbstractController
{
    /** @var Processor */
    private $processor;
    /** @var Client */
    private $client;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var string */
    private $configType;
    /** @var string */
    private $configIndex;

    public function __construct(Processor $processor, Client $client, ContentTypeService $contentTypeService, $configType, $configIndex)
    {
        $this->processor = $processor;
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->configType = $configType;
        $this->configIndex = $configIndex;
    }

    /**
     * @param string $hash
     * @param string hash_config
     * @param string $filename
     * @param Request $request
     * @return Response
     *
     * @Route("/data/asset/{hash_config}/{hash}/{filename}" , name="ems_asset", methods={"GET","HEAD"})
     * @Route("/public/asset/{hash_config}/{hash}/{filename}" , name="ems_asset_public", methods={"GET","HEAD"})
     */
    public function assetAction(string $hash, string $hash_config, string $filename, Request $request) {
        return $this->processor->getResponse($request, $hash, $hash_config, $filename);
    }


    /**
	 * @Route("/asset/{processor}/{hash}", name="ems_asset_processor")
	 */
	public function assetProcessorAction(Request $request, string $processor, string $hash): Response
	{
	    @trigger_error(sprintf('The "%s::assetProcessorAction" controller is deprecated. Used "%s::assetAction" instead.', AssetController::class, AssetController::class), E_USER_DEPRECATED);

        return $this->processor->createResponse($request, $processor, $hash, $this->getOptions($processor));
	}

	private function getOptions(string $processor): array
    {
        @trigger_error(sprintf('The "%s::getOptions" function is deprecated and should not be used anymore.', AssetController::class, AssetController::class), E_USER_DEPRECATED);

        if (null == $this->configType) {
            return [];
        }

        $contentType = $this->contentTypeService->getByName($this->configType);

        if (!$contentType) {
            return [];
        }

        try {
            $result = $this->client->search([
                'size' => 1,
                'type' => $contentType->getName(),
                'index' => $this->configIndex ? $this->configIndex : $contentType->getEnvironment()->getAlias(),
                'body' => '{
                   "query": {
                      "term": {
                         "_identifier": {
                            "value": ' . json_encode($processor) . '
                         }
                      }
                   }
                }',
            ]);

            if ($result['hits']['total'] == 0) {
                return [];
            }

            $defaults = Config::getDefaults();

            // removes invalid options like _sha1, _finalized_by, ..
            return array_intersect_key($result['hits']['hits'][0]['_source'] + $defaults, $defaults);
        } catch (\Exception $e) {
            return [];
        }
    }
}
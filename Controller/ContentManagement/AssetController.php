<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Elasticsearch\Client;
use EMS\CommonBundle\Storage\Processor\Config;
use EMS\CommonBundle\Storage\Processor\Processor;
use EMS\CoreBundle\Service\ContentTypeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
     * @Route("/asset/{processor}/{hash}", name="ems_asset_processor")
     */
    public function assetProcessorAction(Request $request, string $processor, string $hash): Response
    {
        return $this->processor->createResponse($request, $processor, $hash, $this->getOptions($processor));
    }

    private function getOptions(string $processor): array
    {
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

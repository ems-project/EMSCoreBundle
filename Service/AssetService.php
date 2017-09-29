<?php
namespace EMS\CoreBundle\Service;



use Elasticsearch\Client;
use EMS\CoreBundle\Entity\AssetProcessorConfig;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Helper\Image;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetService {
	
	/**@var Client */
	private $client;
	/**@var FileService */
	private $fileService;
	/**@var RequestStack */
	private $requestStack;
	
	public function __construct(Client $client, FileService $fileService, RequestStack $requestStack) {
		$this->fileService = $fileService;
		$this->client= $client;
		$this->requestStack= $requestStack;
	}
	
	public function getAssetResponse(array $config, $assetHash) {
		
		$lastUpdateDate = $this->fileService->getLastUpdateDate($assetHash, $config['identifier']);
		
		if($lastUpdateDate) {
			$request = $this->requestStack->getCurrentRequest();
			$versionInBrowser = strtotime($request->headers->get('if-modified-since', 'Wed, 09 Feb 1977 16:00:00 GMT'));
			
			$lastUpdateDateConfig = strtotime($config['last_update_date']);
			
			if ($versionInBrowser >= $lastUpdateDate && $lastUpdateDate >= $lastUpdateDateConfig) {
				//http://stackoverflow.com/questions/10847157/handling-if-modified-since-header-in-a-php-script
				$response = new Response();
				$response->setNotModified();
				return $response;
			}
		}
	
		$generatedFileName = false;
		$cachedFile = 1;
		if($lastUpdateDate < $lastUpdateDateConfig) {
			switch ($config['type']) {
				case 'image':
					$generatedFileName= $this->imageAssetResponse($config, $assetHash);
					break;
				default:
					throw new NotFoundHttpException('Asset processor type not found');
			}
			
			if(!$generatedFileName) {
				throw new \Exception('ems was not able to generate the asset');
			}			
			$cachedFile = $this->fileService->create($assetHash, $generatedFileName, $config['identifier']);
		}
		
		
		$response = new BinaryFileResponse($cachedFile?$this->fileService->getFile($assetHash, $config['identifier']):$generatedFileName);
		// set headers and output image
		$response->headers->set('X-EMS-CACHED-FILES', $cachedFile);
		
		// Set cache settings in one call
		$response->setCache(array(
				'etag'          => $config['identifier'].'_'. $assetHash,
				'last_modified' => new \DateTime(),
				'max_age'       => 10,
				's_maxage'      => 10,
				//'public'        => true,
				'private'    => true,
		));
		return $response;
	}
	
	
	public function imageAssetResponse(array $processorConfig, $hash) {
		
		$file = $this->fileService->getFile($hash);
		if(isset($processorConfig['watermark']['sha1'])) {
			$processorConfig['watermark']['path'] = $this->fileService->getFile($processorConfig['watermark']['sha1']);
		}
		$file = $this->fileService->getFile($hash);
		$image = new Image($file, $processorConfig);
		return $image->generateImage();
	}
	
	
}
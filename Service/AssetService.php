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
use Symfony\Component\HttpKernel\KernelInterface;

class AssetService {
	
	/**@var Client */
	private $client;
	/**@var FileService */
	private $fileService;
	/**@var RequestStack */
	private $requestStack;
	/**@var KernelInterface */
	private $kernel;
	
	public function __construct(Client $client, FileService $fileService, RequestStack $requestStack, KernelInterface $kernel) {
		$this->fileService = $fileService;
		$this->client= $client;
		$this->requestStack= $requestStack;
		$this->kernel = $kernel;
	}
	
	public function getAssetResponse(array $config, $assetHash, $type) {
		
		$lastUpdateDate = $this->fileService->getLastUpdateDate($assetHash, $config['_identifier']);
		$lastUpdateDateConfig = strtotime($config['_last_update_date']);
		
		if($lastUpdateDate) {
			$request = $this->requestStack->getCurrentRequest();
			$versionInBrowser = strtotime($request->headers->get('if-modified-since', 'Wed, 09 Feb 1977 16:00:00 GMT'));
			
			
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
			switch ($config['_config_type']) {
				case 'image':
					$generatedFileName= $this->imageAssetResponse($config, $assetHash, $type);
					break;
				default:
					throw new NotFoundHttpException('Asset processor type not found');
			}
			
			if(!$generatedFileName) {
				throw new \Exception('ems was not able to generate the asset');
			}			
			$cachedFile = $this->fileService->create($assetHash, $generatedFileName, $config['_identifier']);
		}


		$response = new BinaryFileResponse($cachedFile?$this->fileService->getFile($assetHash, $config['_identifier']):$generatedFileName);
		// set headers and output image
		$response->headers->set('X-EMS-CACHED-FILES', $cachedFile);
        $response->headers->set('Content-Type', $this->getMimeType($config, $type));
		
		// Set cache settings in one call
		$response->setCache(array(
				'etag'          => $config['_identifier'].'_'. $assetHash,
				'last_modified' => new \DateTime(),
				'max_age'       => 10,
				's_maxage'      => 10,
				//'public'        => true,
				'private'    => true,
		));
		return $response;
	}
	
	
	public function imageAssetResponse(array $processorConfig, $hash, $type) {
		
		$file = $this->fileService->getFile($hash);
		if( preg_match('/image\/svg.*/', $type)){
			$path = tempnam(sys_get_temp_dir(), 'ems_image');
			copy($file, $path);
			return $path;
		}
		
		if(isset($processorConfig['_watermark']['sha1'])) {
			$processorConfig['_watermark']['_path'] = $this->fileService->getFile($processorConfig['_watermark']['sha1']);
		}
		
		
		$image = new Image($this->kernel, $file, $processorConfig);
		return $image->generateImage();
	}


    public function getMimeType(array $processorConfig, $type) {
        if( preg_match('/image\/svg.*/', $type)){
            return $type;
        }

        return Image::getMimeType($processorConfig);
    }
	
}
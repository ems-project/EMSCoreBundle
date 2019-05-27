<?php
namespace EMS\CoreBundle\Controller;

use Elasticsearch\Client;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\AssetService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\HelperService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SearchOptionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\SortOptionService;
use EMS\CoreBundle\Service\UserService;
use EMS\CoreBundle\Service\WysiwygProfileService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Translation\TranslatorInterface;

class AppController extends Controller
{

    /**@var LoggerInterface*/
    private $logger;

    /**
     * @var FormRegistryInterface
     */
    private $formRegistry;

    /** @var RequestRuntime */
    protected $requestRuntime;

    public function __construct(LoggerInterface $logger, FormRegistryInterface $formRegistry, RequestRuntime $requestRuntime)
    {
        $this->logger = $logger;
        $this->formRegistry = $formRegistry;
        $this->requestRuntime = $requestRuntime;
    }
    
    /**
     * @return TranslatorInterface
     */
    protected function getTranslator()
    {
        return $this->get('translator');
    }
    
    /**
     * @return Client
     */
    protected function getElasticsearch()
    {
        return $this->get('app.elasticsearch');
    }
    
    /**
     * @return ElasticsearchService
     */
    protected function getElasticsearchService()
    {
        return $this->get('ems.service.elasticsearch');
    }
    
    /**
     * @return WysiwygProfileService
     */
    protected function getWysiwygProfileService()
    {
        return $this->get('ems.service.wysiwyg_profile');
    }
    
    /**
     * @return SortOptionService
     */
    protected function getSortOptionService()
    {
        return $this->get('ems.service.sort_option');
    }

    /**
     * @return AggregateOptionService
     */
    protected function getAggregateOptionService()
    {
        return $this->get('ems.service.aggregate_option');
    }

    /**
     * @return SearchFieldOptionService
     */
    protected function getSearchFieldOptionService()
    {
        return $this->get('ems.service.search_field_option');
    }
    
    /**
     * @return WysiwygStylesSetService
     */
    protected function getWysiwygStylesSetService()
    {
        return $this->get('ems.service.wysiwyg_styles_set');
    }

    /**
     * @return AuthorizationChecker
     */
    protected function getAuthorizationChecker()
    {
        return $this->get('security.authorization_checker');
    }

    protected function getSecurityEncoder(): EncoderFactoryInterface
    {
        return $this->get('security.encoder_factory');
    }
    
    /**
     * @return UserService
     */
    protected function getUserService()
    {
        return $this->get('ems.service.user');
    }

    
    /**
     * @return NotificationService
     */
    protected function getNotificationService()
    {
        return $this->get('ems.service.notification');
    }
    
    /**
     * @return \Twig_Environment
     */
    protected function getTwig()
    {
        return $this->container->get('twig');
    }
    
    /**
     * @return SearchService
     */
    protected function getSearchService()
    {
        return $this->container->get('ems.service.search');
    }
    
    /**
     * @return HelperService
     */
    protected function getHelperService()
    {
        return $this->container->get('ems.service.helper');
    }
        
    /**
     * @return AliasService
     */
    protected function getAliasService()
    {
            return $this->container->get('ems.service.alias')->build();
    }

    /**
     * @param string $fieldTypeNameOrServiceName
     * @return DataFieldType
     * @throws ElasticmsException
     */
    protected function getDataFieldType(string $fieldTypeNameOrServiceName): DataFieldType
    {
        $dataFieldType = $$this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
        if($dataFieldType instanceof DataFieldType){
            return $dataFieldType;
        }
        throw new ElasticmsException(sprintf('Expecting a DataFieldType instance, got a %s', get_class($dataFieldType) ) );
    }
    
    /**
     * Get the injected logger
     *
     * @return LoggerInterface
     *
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param string $service
     * @param string $arguments
     *
     * @return RedirectResponse
     */
    protected function startJob($service, $arguments)
    {
        /** @var JobService $jobService */
        $jobService = $this->container->get('ems.service.job');
        $job = $jobService->createService($this->getUser(), $service, $arguments);

        $this->addFlash('notice', 'A job has been prepared');
        
        return $this->redirectToRoute('job.status', [
            'job' => $job->getId(),
        ]);
    }

    public static function getFormatedTimestamp()
    {
        return date('_Ymd_His');
    }
    
    protected function getGUID()
    {
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid =
         substr($charid, 0, 8).$hyphen
        .substr($charid, 8, 4).$hyphen
        .substr($charid, 12, 4).$hyphen
        .substr($charid, 16, 4).$hyphen
        .substr($charid, 20, 12);
        return $uuid;
    }


    
    /**
     *
     * @return AssetExtratorService
     */
    public function getAssetExtractorService()
    {
        return $this->get('ems.service.asset_extractor');
    }
    
    /**
     *
     * @return DataService
     */
    public function getDataService()
    {
        return $this->get('ems.service.data');
    }
    
    /**
     *
     * @return PublishService
     */
    public function getPublishService()
    {
        return $this->get('ems.service.publish');
    }

    /**
     *
     * @return ContentTypeService
     */
    public function getContentTypeService()
    {
        return $this->get('ems.service.contenttype');
    }
    
    /**
     *
     * @return EnvironmentService
     */
    public function getEnvironmentService()
    {
        return $this->get('ems.service.environment');
    }

    protected function returnJson($success, $template = '@EMSCore/ajax/notification.json.twig')
    {
        $response = $this->render($template, [
            'success' => $success,
        ]);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}

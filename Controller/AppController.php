<?php
namespace EMS\CoreBundle\Controller;

use Elasticsearch\Client;
use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\AssetExtractorService;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig_Environment;

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
     * @deprecated use dependency injection
     * @return TranslatorInterface
     */
    protected function getTranslator()
    {
        return $this->get('translator');
    }
    
    /**
     * @deprecated use dependency injection
     * @return Client
     */
    protected function getElasticsearch()
    {
        return $this->get('app.elasticsearch');
    }
    
    /**
     * @deprecated use dependency injection
     * @return ElasticsearchService
     */
    protected function getElasticsearchService()
    {
        return $this->get('ems.service.elasticsearch');
    }
    
    /**
     * @deprecated use dependency injection
     * @return WysiwygProfileService
     */
    protected function getWysiwygProfileService()
    {
        return $this->get('ems.service.wysiwyg_profile');
    }
    
    /**
     * @deprecated use dependency injection
     * @return SortOptionService
     */
    protected function getSortOptionService()
    {
        return $this->get('ems.service.sort_option');
    }

    /**
     * @deprecated use dependency injection
     * @return AggregateOptionService
     */
    protected function getAggregateOptionService()
    {
        return $this->get('ems.service.aggregate_option');
    }

    /**
     * @deprecated use dependency injection
     * @return SearchFieldOptionService
     */
    protected function getSearchFieldOptionService()
    {
        return $this->get('ems.service.search_field_option');
    }
    
    /**
     * @deprecated use dependency injection
     * @return WysiwygStylesSetService
     */
    protected function getWysiwygStylesSetService()
    {
        return $this->get('ems.service.wysiwyg_styles_set');
    }

    /**
     * @deprecated use dependency injection
     * @return AuthorizationChecker
     */
    protected function getAuthorizationChecker()
    {
        return $this->get('security.authorization_checker');
    }

    /**
     * @deprecated use dependency injection
     * @return EncoderFactoryInterface
     */
    protected function getSecurityEncoder()
    {
        return $this->get('security.encoder_factory');
    }
    
    /**
     * @deprecated use dependency injection
     * @return UserService
     */
    protected function getUserService()
    {
        return $this->get('ems.service.user');
    }

    
    /**
     * @deprecated use dependency injection
     * @return NotificationService
     */
    protected function getNotificationService()
    {
        return $this->get('ems.service.notification');
    }
    
    /**
     * @deprecated use dependency injection
     * @return Twig_Environment
     */
    protected function getTwig()
    {
        return $this->container->get('twig');
    }
    
    /**
     * @deprecated use dependency injection
     * @return SearchService
     */
    protected function getSearchService()
    {
        return $this->container->get('ems.service.search');
    }
    
    /**
     * @deprecated use dependency injection
     * @return HelperService
     */
    protected function getHelperService()
    {
        return $this->container->get('ems.service.helper');
    }
        
    /**
     * @deprecated use dependency injection
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
        $dataFieldType = $this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            return $dataFieldType;
        }
        throw new ElasticmsException(sprintf('Expecting a DataFieldType instance, got a %s', get_class($dataFieldType)));
    }
    
    /**
     * @deprecated use dependency injection
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    public static function getFormatedTimestamp()
    {
        return date('_Ymd_His');
    }
    
    protected function getGUID()
    {
        mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
        $charid = strtolower(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid =
         substr($charid, 0, 8) . $hyphen
        . substr($charid, 8, 4) . $hyphen
        . substr($charid, 12, 4) . $hyphen
        . substr($charid, 16, 4) . $hyphen
        . substr($charid, 20, 12);
        return $uuid;
    }


    
    /**
     * @deprecated use dependency injection
     * @return AssetExtractorService
     */
    public function getAssetExtractorService()
    {
        return $this->get('ems.service.asset_extractor');
    }
    
    /**
     * @deprecated use dependency injection
     * @return DataService
     */
    public function getDataService()
    {
        return $this->get('ems.service.data');
    }
    
    /**
     * @deprecated use dependency injection
     * @return PublishService
     */
    public function getPublishService()
    {
        return $this->get('ems.service.publish');
    }

    /**
     * @deprecated use dependency injection
     * @return ContentTypeService
     */
    public function getContentTypeService()
    {
        return $this->get('ems.service.contenttype');
    }
    
    /**
     * @deprecated use dependency injection
     * @return EnvironmentService
     */
    public function getEnvironmentService()
    {
        return $this->get('ems.service.environment');
    }



    protected function returnJsonResponse(Request $request, bool $success, array $body = [])
    {
        $body['success'] = $success;
        $body['acknowledged'] = true;
        foreach (['notice', 'warning', 'error'] as $level) {
            $messages = $request->getSession()->getFlashBag()->get($level);
            if (!empty($messages)) {
                $body[$level] = $messages;
            }
        }

        $response = new Response();
        $response->setContent(json_encode($body));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
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

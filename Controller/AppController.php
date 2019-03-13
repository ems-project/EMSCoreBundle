<?php
namespace EMS\CoreBundle\Controller;

use Elasticsearch\Client;
use EMS\CoreBundle\Entity\SearchFieldOption;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\AssetService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Service\HelperService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\UserService;
use EMS\CoreBundle\Service\WysiwygProfileService;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use EMS\CoreBundle\Service\AssetExtratorService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\SearchOptionService;
use EMS\CoreBundle\Service\SortOptionService;

class AppController extends Controller
{

    /**@var LoggerInterface*/
    private $logger;

    /**
     * @var FormRegistryInterface
     */
    private $formRegistry;

    public function __construct(LoggerInterface $logger, FormRegistryInterface $formRegistry)
    {
        $this->logger = $logger;
        $this->formRegistry = $formRegistry;
    }


    /**
     * @Route("/js/app.js", name="app.js"))
     */
    public function javascriptAction()
    {
        return $this->render('@EMSCore/app/app.js.twig');
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
     * @return AssetService
     */
    protected function getAssetService()
    {
        return $this->get('ems.service.asset');
    }
    
    /**
     * @return ElasticsearchService
     */
    protected function getElasticsearchService()
    {
        return $this->get('ems.service.elasticsearch');
    }
    
    /**
     * @return FileService
     */
    protected function getFileService()
    {
        return $this->get('ems.service.file');
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
    
    /**
     *
     * @return EncoderFactoryInterface
     */
    protected function getSecurityEncoder()
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
     *
     * @param string $fieldTypeNameOrServiceName
     *
     * @return DataFieldType
     */
    protected function getDataFieldType($fieldTypeNameOrServiceName)
    {
        return $this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
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
        /** @var $jobService JobService */
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

    /**
     *
     */
    protected function returnJson($success, $template = '@EMSCore/ajax/notification.json.twig')
    {
        $response = $this->render($template, [
            'success' => $success,
        ]);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}

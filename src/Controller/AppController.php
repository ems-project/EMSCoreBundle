<?php

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\AliasService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\HelperService;
use EMS\CoreBundle\Service\NotificationService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchFieldOptionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\UserService;
use EMS\CoreBundle\Service\WysiwygStylesSetService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Twig_Environment;

class AppController extends Controller
{
    /** @var LoggerInterface */
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
     *
     * @return SearchFieldOptionService
     */
    protected function getSearchFieldOptionService()
    {
        return $this->get('ems.service.search_field_option');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return WysiwygStylesSetService
     */
    protected function getWysiwygStylesSetService()
    {
        return $this->get('ems.service.wysiwyg_styles_set');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return AuthorizationChecker
     */
    protected function getAuthorizationChecker()
    {
        return $this->get('security.authorization_checker');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return UserService
     */
    protected function getUserService()
    {
        return $this->get('ems.service.user');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return NotificationService
     */
    protected function getNotificationService()
    {
        return $this->get('ems.service.notification');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return Twig_Environment
     */
    protected function getTwig()
    {
        return $this->container->get('twig');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return SearchService
     */
    protected function getSearchService()
    {
        return $this->container->get('ems.service.search');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return HelperService
     */
    protected function getHelperService()
    {
        return $this->container->get('ems.service.helper');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return AliasService
     */
    protected function getAliasService()
    {
        return $this->container->get('ems.service.alias')->build();
    }

    /**
     * @throws ElasticmsException
     */
    protected function getDataFieldType(string $fieldTypeNameOrServiceName): DataFieldType
    {
        $dataFieldType = $this->formRegistry->getType($fieldTypeNameOrServiceName)->getInnerType();
        if ($dataFieldType instanceof DataFieldType) {
            return $dataFieldType;
        }
        throw new ElasticmsException(\sprintf('Expecting a DataFieldType instance, got a %s', \get_class($dataFieldType)));
    }

    /**
     * @deprecated use dependency injection
     *
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    public static function getFormatedTimestamp()
    {
        return \date('_Ymd_His');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return DataService
     */
    public function getDataService()
    {
        return $this->get('ems.service.data');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return PublishService
     */
    public function getPublishService()
    {
        return $this->get('ems.service.publish');
    }

    /**
     * @deprecated use dependency injection
     *
     * @return ContentTypeService
     */
    public function getContentTypeService()
    {
        return $this->get('ems.service.contenttype');
    }

    /**
     * @deprecated use dependency injection
     *
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
        $response->setContent(\json_encode($body));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}

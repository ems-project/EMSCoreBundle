<?php

namespace EMS\CoreBundle\Controller;

use EMS\CommonBundle\Twig\RequestRuntime;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Form\DataField\DataFieldType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppController extends AbstractController
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

    protected function returnJsonResponse(Request $request, bool $success, array $body = []): Response
    {
        return self::jsonResponse($request, $success, $body);
    }

    public static function jsonResponse(Request $request, bool $success, array $body = []): Response
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

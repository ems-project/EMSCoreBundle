<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class DocumentationController extends AbstractController
{
    public function __construct(
        private readonly string $templateNamespace,
        private readonly RouterInterface $router
    ) {
    }

    public function getDocumentation(Request $request): Response
    {
        $format = $request->getRequestFormat();

        if ('json' === $format) {
            $paths = [];
            $tags = [];

            foreach ($this->getRoutes() as $name => $route) {
                $path = $route->getPath();
                $methods = $route->getMethods();
                $controller = $route->getDefault('_controller') ?? 'Not defined';

                $tag = 'Default';
                if (\str_contains((string) $controller, 'Controller')) {
                    $parts = \explode('\\', (string) $controller);
                    $controllerName = \end($parts);
                    $tag = \str_replace('Controller', '', \explode('::', $controllerName)[0]);
                }

                $tags[$tag] = [
                    'name' => $tag,
                    'description' => 'Endpoints related to '.$tag,
                ];

                foreach ($methods as $method) {
                    $paths[$path][\strtolower($method)] = [
                        'tags' => [$tag],
                        'summary' => $name,
                        'responses' => [
                            '200' => [
                                'description' => 'Success response for route '.$name,
                            ],
                            '401' => [
                                'description' => 'Unauthorized',
                            ],
                        ],
                    ];
                }
            }

            $openApi = [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Dynamically Generated API',
                    'description' => 'OpenAPI documentation based on Symfony routes',
                    'version' => '1.0.0',
                ],
                'tags' => \array_values($tags),
                'paths' => $paths,
                'components' => [
                    'securitySchemes' => [
                        'XAuthToken' => [
                            'type' => 'apiKey',
                            'in' => 'header',
                            'name' => 'X-Auth-Token',
                        ],
                    ],
                ],
                'security' => [
                    [
                        'XAuthToken' => [],
                    ],
                ],
            ];

            return new JsonResponse($openApi);
        }

        return $this->render(\sprintf('@%s/api/documentation.html.twig', $this->templateNamespace));
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        $routes = $this->router->getRouteCollection()->all();

        return \array_filter($routes, static fn (Route $route) => $route->getOption('openapi'));
    }
}

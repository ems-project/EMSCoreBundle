<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

class DocumentationController extends AbstractController
{
    public function __construct(
        private readonly RouterInterface $router
    ) {
    }

    public function getDocumentation(Request $request): Response
    {
        $format = $request->getRequestFormat();

        if ('html' === $format) {
            return $this->render('@EMSAdminUI/swagger/api/documentation.html.twig');
        }

        if ('json' !== $format) {
            throw new BadRequestHttpException(\sprintf('Invalid documentation format "%s".', $format));
        }

        $paths = [];
        $tags = [];
        foreach ($this->getRoutes() as $name => $route) {
            $path = $route->getPath();
            $methods = $route->getMethods();
            $controller = $this->getControllerName($route);

            $tag = 'Default';
            if (\str_contains($controller, 'Controller')) {
                $parts = \explode('\\', $controller);
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
                    'parameters' => $this->getParameters($route),
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

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        $routes = $this->router->getRouteCollection()->all();

        return \array_filter($routes, static fn (Route $route): bool => true === $route->getOption('openapi'));
    }

    private function getControllerName(Route $route): string
    {
        $controller = $route->getDefault('_controller');

        return match (true) {
            null === $controller => 'Not defined',
            \is_string($controller) => $controller,
            \is_array($controller) => $this->getArrayControllerName($controller),
            \is_object($controller) => $controller::class,
            default => \get_debug_type($controller),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getParameters(Route $route): array
    {
        $parameters = [];
        $requirements = $route->getRequirements();
        $defaults = $route->getDefaults();

        foreach ($route->compile()->getPathVariables() as $name) {
            $requirement = $requirements[$name] ?? null;
            $default = $defaults[$name] ?? null;
            $schema = [
                'type' => $this->getParameterType($default, $requirement),
            ];

            if (null !== $requirement) {
                $schema['pattern'] = $requirement;
            }

            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    private function getParameterType(mixed $default, ?string $requirement): string
    {
        if (\is_int($default)) {
            return 'integer';
        }

        if (null !== $requirement && \preg_match('/^\\d(?:\\D|$)/', $requirement)) {
            return 'integer';
        }

        return 'string';
    }

    /**
     * @param array<mixed> $controller
     */
    private function getArrayControllerName(array $controller): string
    {
        $parts = [];
        foreach ($controller as $part) {
            if (\is_string($part) || \is_int($part)) {
                $parts[] = (string) $part;
            } elseif (\is_object($part)) {
                $parts[] = $part::class;
            } else {
                $parts[] = \get_debug_type($part);
            }
        }

        return [] === $parts ? 'Not defined' : \implode('::', $parts);
    }
}

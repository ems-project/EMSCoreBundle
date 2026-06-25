<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Service\UserService;
use EMS\Helpers\Html\Headers;
use EMS\Helpers\Html\MimeTypes;
use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class McpController
{
    public function __construct(
        private readonly Server $server,
        private readonly ServerRequestCreator $serverRequestCreator,
        private readonly Psr17Factory $psr17Factory,
        private readonly LoggerInterface $logger,
        private readonly LoggerInterface $auditLogger,
        private readonly UserService $userService,
    ) {
    }

    public function handle(Request $request): Response
    {
        $context = [
            'methods' => $this->extractMethodNames($request),
            'username' => $this->userService->getCurrentUser()->getUsername(),
        ];

        $this->logger->info('mcp.request.received', $context);
        $this->auditLogger->info('mcp.request.received', $context);

        $psrRequest = $this->serverRequestCreator->fromArrays(
            $request->server->all(),
            $request->headers->all(),
            $request->cookies->all(),
            $request->query->all(),
            [] !== $request->request->all() ? $request->request->all() : null,
            $request->files->all(),
            $request->getContent()
        );

        $psrResponse = $this->server->run(new StreamableHttpTransport(
            $psrRequest,
            $this->psr17Factory,
            $this->psr17Factory,
            $this->logger,
            [
                new CorsMiddleware(),
                new ProtocolVersionMiddleware(),
            ],
        ));

        $response = $this->toSymfonyResponse($psrResponse);

        $this->logger->info('mcp.request.completed', [...$context, 'status_code' => $response->getStatusCode()]);
        $this->auditLogger->info('mcp.request.completed', [...$context, 'status_code' => $response->getStatusCode()]);

        return $response;
    }

    /**
     * @return string[]
     */
    private function extractMethodNames(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        $decoded = \json_decode($content, true);
        if (!\is_array($decoded)) {
            return [];
        }

        if (\array_is_list($decoded)) {
            return \array_values(\array_filter(\array_map(static fn (mixed $item): ?string => \is_array($item) && \is_string($item['method'] ?? null) ? $item['method'] : null, $decoded)));
        }

        return \is_string($decoded['method'] ?? null) ? [$decoded['method']] : [];
    }

    private function toSymfonyResponse(ResponseInterface $response): Response
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = 1 === \count($values) ? $values[0] : $values;
        }

        if (MimeTypes::TEXT_EVENT_STREAM->value === $response->getHeaderLine(Headers::CONTENT_TYPE)) {
            return new StreamedResponse(
                static fn () => print $response->getBody()->getContents(),
                $response->getStatusCode(),
                $headers
            );
        }

        return new Response(
            $response->getBody()->getContents(),
            $response->getStatusCode(),
            $headers
        );
    }
}

<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Elasticsearch;

use Elastica\Bulk;
use Elastica\Bulk\Action;
use Elastica\Bulk\ResponseSet;
use Elastica\Exception\Bulk\ResponseException;
use Elastica\JSON;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;

class Bulker
{
    private int $counter = 0;
    private int $size = 500;
    private bool $sign = true;
    private bool $publish = true;
    /** @var array<mixed> */
    private array $errors = [];
    private Bulk $bulk;

    public function __construct(private readonly Client $client, private LoggerInterface $logger, private readonly DataService $dataService)
    {
        $this->bulk = new Bulk($this->client);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setLogger(LoggerInterface $logger): Bulker
    {
        $this->logger = $logger;

        return $this;
    }

    public function setSize(int $size): Bulker
    {
        $this->size = $size;

        return $this;
    }

    public function setSign(bool $sign): Bulker
    {
        $this->sign = $sign;

        return $this;
    }

    public function setPublish(bool $publish): Bulker
    {
        $this->publish = $publish;

        return $this;
    }

    public function delete(string $index, string $ouuid): bool
    {
        $action = $this->createAction($index, $ouuid);
        $action->setOpType(Action::OP_TYPE_DELETE);

        $this->bulk->addAction($action);
        ++$this->counter;

        return $this->send();
    }

    /**
     * @param array<mixed> $body
     */
    public function index(string $contentType, string $ouuid, string $index, array &$body, bool $upsert = false): bool
    {
        if ($this->sign) {
            $this->dataService->signRaw($body);
        }

        $body[Mapping::CONTENT_TYPE_FIELD] = $contentType;

        if ($this->publish) {
            $body[Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTimeInterface::ATOM);
        }

        $action = $this->createAction($index, $ouuid);

        // @todo check this with a elastica 7
        $source = JSON::stringify($body, JSON_UNESCAPED_UNICODE); // elastica actions do not support fields named 'doc' or 'doc_as_upsert'

        if ($upsert) {
            $action->setOpType(Action::OP_TYPE_UPDATE);
            $action->setSource(['doc' => $source, 'doc_as_upsert' => true]);
        } else {
            $action->setOpType(Action::OP_TYPE_INDEX);
            $action->setSource($source);
        }
        $this->bulk->addAction($action);
        ++$this->counter;

        return $this->send();
    }

    public function indexDocument(DocumentInterface $document, string $index, bool $upsert = false): bool
    {
        $body = $document->getSource();

        return $this->index($document->getContentType(), $document->getId(), $index, $body, $upsert);
    }

    /**
     * @throws NoNodesAvailableException
     */
    public function send(bool $force = false, bool $retry = false): bool
    {
        if (0 === $this->counter) {
            return false;
        }

        if (!$force && $this->counter < $this->size) {
            return false;
        }

        try {
            $response = $this->bulk->send();
            $this->logResponse($response);
            $this->bulk = new Bulk($this->client);
            $this->counter = 0;
            unset($response);
        } catch (NoNodesAvailableException $e) {
            if (!$retry) {
                $this->logger->info('No nodes available retry');
                \sleep(10);

                return $this->send($force, true);
            } else {
                throw $e;
            }
        } catch (ResponseException $e) {
            $this->counter = 0;
            $exceptions = $e->getActionExceptions();

            if (\count($exceptions) > 0) {
                $this->logger->critical('Bulk response exceptions ({count}) ', [
                    'count' => \count($exceptions),
                ]);
                $this->logger->critical('First exceptions: {message}', [
                    'message' => $exceptions[0]->getMessage(),
                ]);
            } else {
                $this->logger->critical($e->getMessage());
            }
        } catch (\Throwable $e) {
            $this->errors[] = $e;
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    private function createAction(string $index, string $ouuid): Action
    {
        $action = new Action();
        $action->setIndex($index);
        $action->setId($ouuid);

        return $action;
    }

    private function logResponse(ResponseSet $response): void
    {
        foreach ($response as $item) {
            if (!$item->hasError()) {
                continue; // no error
            }

            $this->errors[] = $item;
            $this->logger->critical('{error}', [
                'error' => $item->getErrorMessage(),
            ]);
        }

        $this->logger->notice('bulked {count} items in {took}ms', [
            'count' => $response,
            'took' => $response->getQueryTime(),
        ]);
    }
}

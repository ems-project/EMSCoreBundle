<?php

namespace EMS\CoreBundle\Elasticsearch;

use Elastica\Bulk;
use Elastica\Bulk\Action;
use Elastica\Bulk\Response;
use Elastica\Bulk\ResponseSet;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Mapping;
use Psr\Log\LoggerInterface;

class Bulker
{
    /** @var LoggerInterface */
    private $logger;
    /** @var DataService */
    private $dataService;
    /** @var Mapping */
    private $mapping;
    /** @var int */
    private $counter = 0;
    /** @var int */
    private $size = 500;
    /** @var bool */
    private $sign = true;
    /** @var array */
    private $errors = [];
    /** @var Bulk */
    private $bulk;
    /** @var Client */
    private $client;

    public function __construct(Client $client, LoggerInterface $logger, DataService $dataService, Mapping $mapping)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->dataService = $dataService;
        $this->mapping = $mapping;
        $this->bulk = new Bulk($this->client);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

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

    public function index(string $contentType, string $ouuid, string $index, array &$body, bool $upsert = false): bool
    {
        $body[Mapping::CONTENT_TYPE_FIELD] = $contentType;
        $body[Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTime::ISO8601);
        if ($this->sign) {
            $this->dataService->signRaw($body);
        }

        $action = new Action();
        $action->setIndex($index);
        $action->setId($ouuid);
        $typePath = $this->mapping->getTypePath($contentType);
        if ('.' !== $typePath) {
            $action->setType($typePath);
        }

        if ($upsert) {
            $action->setOpType(Action::OP_TYPE_UPDATE);
            $action->setSource(['doc' => $body, 'doc_as_upsert' => true]);
        } else {
            $action->setOpType(Action::OP_TYPE_INDEX);
            $action->setSource($body);
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
        } catch (\Throwable $e) {
            $this->errors[] = $e;
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    private function logResponse(ResponseSet $response)
    {
        foreach ($response as $item) {
            if (!$item instanceof Response) {
                continue;
            }
            if (!$item->hasError()) {
                continue; //no error
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

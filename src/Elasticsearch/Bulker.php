<?php

namespace EMS\CoreBundle\Elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Factory;
use Psr\Log\LoggerInterface;

class Bulker
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var array
     */
    private $options;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $params = ['body' => []];

    /**
     * @var int
     */
    private $counter = 0;

    /**
     * @var int
     */
    private $size = 500;

    /**
     * @var bool
     */
    private $singleIndex = false;

    /**
     * @var bool
     */
    private $enableSha1 = true;

    /**
     * @var array
     */
    private $errors;

    public function __construct(Factory $factory, array $options, LoggerInterface $logger)
    {
        $this->factory = $factory;
        $this->options = $options;
        $this->logger = $logger;

        $this->client = $this->getClient();
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return Bulker
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    public function setSize(int $size): Bulker
    {
        $this->size = $size;

        return $this;
    }

    public function setSingleIndex(bool $singleIndex): Bulker
    {
        $this->singleIndex = $singleIndex;

        return $this;
    }

    public function setEnableSha1(bool $enableSha1): Bulker
    {
        $this->enableSha1 = $enableSha1;

        return $this;
    }

    public function index(array $config, array $body, bool $upsert = false): bool
    {
        if ($this->enableSha1) {
            $body['_sha1'] = sha1(json_encode($body));
        }

        if ($upsert) {
            $this->params['body'][] = ['update' => $config];
            $this->params['body'][] = ['doc' => $body, 'doc_as_upsert' => true];
        } else {
            $this->params['body'][] = ['index' => $config];
            $this->params['body'][] = $body;
        }

        ++$this->counter;

        return $this->send();
    }

    public function indexDocument(DocumentInterface $document, string $index, bool $upsert = false): bool
    {
        $config = [
            '_index' => $index,
            '_type' => ($this->singleIndex ? 'doc' : $document->getType()),
            '_id' => $document->getId(),
        ];
        $body = $document->getSource();

        if ($this->singleIndex) {
            $body = array_merge(['_contenttype' => $document->getType()], $body);
        }

        return $this->index($config, $body, $upsert);
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
            $response = $this->client->bulk($this->params);
            $this->logResponse($response);

            $this->params = ['body' => []];
            $this->counter = 0;
            unset($response);
        } catch (NoNodesAvailableException $e) {
            if (!$retry) {
                $this->logger->info('No nodes available trying new client');
                $this->client = $this->getClient();

                return $this->send($force, true);
            } else {
                throw $e;
            }
        } catch (\Exception $e) {
            $this->errors[] = $e;
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    private function getClient(): Client
    {
        return $this->factory->fromConfig($this->options);
    }

    private function logResponse(array $response)
    {
        foreach ($response['items'] as $item) {
            $action = array_shift($item);

            if (!isset($action['error'])) {
                continue; //no error
            }

            $this->errors[] = $action;
            $this->logger->critical('{type} {id} : {error} {reason}', [
                'type' => $action['_type'],
                'id' => $action['_id'],
                'error' => $action['error']['type'],
                'reason' => $action['error']['reason'],
            ]);
        }

        $this->logger->debug('bulked {count} items in {took}ms', [
            'count' => count($response['items']),
            'took' => $response['took'],
        ]);
    }
}

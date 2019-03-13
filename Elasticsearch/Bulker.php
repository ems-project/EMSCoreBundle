<?php

namespace EMS\CoreBundle\Elasticsearch;

use Elasticsearch\Client;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;
use Psr\Log\LoggerInterface;

class Bulker
{
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
     * @param Client          $client
     * @param LoggerInterface $logger
     */
    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return Bulker
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param int $size
     *
     * @return Bulker
     */
    public function setSize(int $size): Bulker
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @param bool $singleIndex
     *
     * @return Bulker
     */
    public function setSingleIndex(bool $singleIndex): Bulker
    {
        $this->singleIndex = $singleIndex;

        return $this;
    }

    /**
     * @param bool $enableSha1
     *
     * @return Bulker
     */
    public function setEnableSha1(bool $enableSha1): Bulker
    {
        $this->enableSha1 = $enableSha1;

        return $this;
    }

    /**
     * @param array $config
     * @param array $body
     *
     * @return bool
     */
    public function index(array $config, array $body): bool
    {
        if ($this->enableSha1) {
            $body['_sha1'] = sha1(json_encode($body));
        }

        $this->params['body'][] = ['index' => $config];
        $this->params['body'][] = $body;

        $this->counter++;

        return $this->send();
    }

    /**
     * @param DocumentInterface $document
     * @param string            $index
     *
     * @return bool
     */
    public function indexDocument(DocumentInterface $document, string $index): bool
    {
        $config = [
            '_index' => $index,
            '_type'  => ($this->singleIndex ? 'doc' : $document->getType()),
            '_id'    => $document->getId(),
        ];
        $body = $document->getSource();

        if ($this->singleIndex) {
            $body = array_merge(['_contenttype' => $document->getType()], $body);
        }

        return $this->index($config, $body);
    }

    /**
     * @param bool $force
     *
     * @return bool
     */
    public function send($force = false): bool
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
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return true;
    }

    /**
     * @param array $response
     */
    private function logResponse(array $response)
    {
        foreach ($response['items'] as $item) {
            $action = array_shift($item);

            if (!isset($action['error'])) {
                continue; //no error
            }

            $this->logger->critical('{type} {id} : {error} {reason}', [
                'type'   => $action['_type'],
                'id'     => $action['_id'],
                'error'  => $action['error']['type'],
                'reason' => $action['error']['reason'],
            ]);
        }

        $this->logger->debug('bulked {count} items in {took}ms', [
            'count' => count($response['items']),
            'took'  => $response['took'],
        ]);
    }
}

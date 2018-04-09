<?php

namespace EMS\CoreBundle\Service;

use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

class BulkerService
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
    private static $params = ['body' => []];

    /**
     * @var int
     */
    private $counter = 0;

    /**
     * @var int
     */
    private $size = 500;

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
     * @return BulkerService
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param int $size
     *
     * @return BulkerService
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @param array $config
     * @param array $body
     *
     * @throws \Exception
     */
    public function index(array $config, array $body)
    {
        self::$params['body'][] = [
            'index' => $config
        ];
        self::$params['body'][] = $body;

        $this->counter++;
        $this->send();
    }

    /**
     * @param bool $force
     *
     * @throws \Exception
     */
    public function send($force = false)
    {
        if (!$force && $this->counter < $this->size) {
            return;
        }

        try {
            $response = $this->client->bulk(self::$params);
            $this->logResponse($response);

            self::$params = ['body' => []];
            $this->counter = 0;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array $response
     */
    private function logResponse(array $response)
    {
        foreach($response['items'] as $item) {
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
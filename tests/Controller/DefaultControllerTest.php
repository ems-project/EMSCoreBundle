<?php

namespace EMS\CoreBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');
        /** @var Response $response */
        $response = $client->getResponse();

        $this->assertTrue($response->isRedirect('/notifications/inbox'));
        $this->assertEquals(302, $response->getStatusCode());
    }
}

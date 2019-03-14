<?php

namespace EMS\CoreBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isRedirect('/notifications/inbox'));
        $this->assertEquals(302, $client->getResponse()->getStatusCode());

    }
}

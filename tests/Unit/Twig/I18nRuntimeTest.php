<?php

namespace EMS\CoreBundle\Tests\Twig;

use EMS\CommonBundle\Common\Standard\Json;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Service\I18nService;
use EMS\CoreBundle\Twig\I18nRuntime;
use PHPUnit\Framework\TestCase;

class I18nRuntimeTest extends TestCase
{
    private I18nService $service;
    private I18nRuntime $i18nRuntime;

    public function setUp(): void
    {
        $this->service = $this->createMock(I18nService::class);
        $this->i18nRuntime = new I18nRuntime($this->service);
    }

    public function testFindAllI18nIsNull()
    {
        $this->service
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn(null);

        $result = $this->i18nRuntime->findAll('unknown');

        $this->assertEquals([], $result);
    }

    public function testFindAllDecodeFalse()
    {
        $i18n = $this->getResults('config');

        $this->service
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn($i18n);

        $result = $this->i18nRuntime->findAll('config');

        $this->assertEquals($i18n->getContent(), $result);
    }

    public function testFindAllDecodeTrue()
    {
        $i18n = $this->getResults('config');

        $this->service
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn($i18n);

        $decodedContent = [];
        foreach ($i18n->getContent() as $content) {
            $decodedContent[] = ['locale' => $content['locale'], 'text' => Json::decode($content['text'])];
        }

        $result = $this->i18nRuntime->findAll('config', true);

        $this->assertEquals($decodedContent, $result);
    }

    private function getResults(string $name = null)
    {
        $dbResults = [
            ['id' => 8, 'created' => '2022-03-02 14:44:56', 'modified' => '2022-03-02 14:44:56', 'identifier' => 'config', 'content' => [['locale' => 'en', 'text' => '{"locales": ["en", "fr","nl","de"]}']]],
        ];

        $results = [];

        foreach ($dbResults as $result) {
            $i18n = (new I18n())
                ->setIdentifier($result['identifier'])
                ->setCreated(new \DateTime($result['created']))
                ->setModified(new \DateTime($result['modified']))
                ->setContent($result['content']);

            if ($name && $i18n->getIdentifier() === $name) {
                return $i18n;
            }

            $results[] = $i18n;
        }

        return $results;
    }
}

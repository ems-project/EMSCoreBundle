<?php

namespace EMS\CoreBundle\Tests\Twig;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Service\I18nService;
use EMS\CoreBundle\Twig\I18nRuntime;
use PHPUnit\Framework\TestCase;

class I18nRuntimeTest extends TestCase
{
    private readonly UserManager $service;
    private I18nRuntime $i18nRuntime;

    public function setUp(): void
    {
        $this->i18nService = $this->createMock(I18nService::class);
        $this->userManager = $this->createMock(UserManager::class);
        $this->i18nRuntime = new I18nRuntime($this->i18nService, $this->userManager);
    }

    public function testFindAllI18nIsNull()
    {
        $this->i18nService
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn(null);

        $result = $this->i18nRuntime->findAll('unknown');

        $this->assertEquals([], $result);
    }

    public function testFindAllDecodeFalse()
    {
        $i18n = $this->getResults('config');

        $this->i18nService
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn($i18n);

        $result = $this->i18nRuntime->findAll('config');

        $content = [];
        \array_map(function ($element) use (&$content) {
            $content[$element['locale']] = $element['text'];
        }, $i18n->getContent());

        $this->assertEquals($content, $result);
    }

    public function testFindAllThrowsRunTimeException()
    {
        $i18n = $this->getResults('invalid');

        $this->i18nService
            ->expects($this->once())
            ->method('getByItemName')
            ->willReturn($i18n);

        $this->expectException(\RuntimeException::class);
        $this->i18nRuntime->findAll('config');
    }

    public function testFallbackLocale()
    {
        $this->i18nService
            ->expects($this->once())
            ->method('getAsList')
            ->willReturn([
                'en' => 'hello in en',
                'fr' => 'hello in fr',
            ]);

        $value = $this->i18nRuntime->i18n('config', 'nl');
        $this->assertEquals('hello in en', $value);
    }

    private function getResults(?string $name = null)
    {
        $dbResults = [
            ['id' => 8, 'created' => '2022-03-02 14:44:56', 'modified' => '2022-03-02 14:44:56', 'identifier' => 'invalid', 'content' => [['locale' => 0, 'text' => '{"locales": ["en", "fr","nl","de"]}']]],
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
